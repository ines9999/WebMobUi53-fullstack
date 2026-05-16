<script setup>
/**
 * AppPollVote.vue
 * ---------------
 * Page de vote pour un sondage, accessible via le lien de partage.
 * Le token dans l'URL identifie le sondage (pas besoin d'être connecté pour y accéder).
 *
 * Comportement :
 *  - Tout le monde peut voir la page et les résultats (si publics)
 *  - Seul un utilisateur connecté peut voter
 *  - Le vote est bloqué si le sondage est expiré ou en brouillon
 *  - Les résultats se rafraîchissent automatiquement toutes les 5 secondes (polling)
 */
import { ref, computed, onUnmounted } from 'vue';
import { useFetchApi } from './composables/useFetchApi';
import PollResults from './components/PollResults.vue';

const props = defineProps({
  token: { type: String, required: true },
  loginUrl: { type: String, default: null },
});

const { fetchApi, fetchApiToRef } = useFetchApi();

// Chargement des données du sondage au démarrage
const { data: pollData, error: pollError } = fetchApiToRef({ url: `/polls/${props.token}` });

// Résultats avec polling toutes les 5 secondes
const results = ref(null);
const resultsError = ref(null);

async function loadResults() {
  try {
    const data = await fetchApi({ url: `/polls/${props.token}/results` });
    results.value = data;
    resultsError.value = null;
  } catch (err) {
    resultsError.value = err;
  }
}

loadResults();
const pollingTimer = setInterval(loadResults, 5000);
onUnmounted(() => clearInterval(pollingTimer));

// Sélection des options pour voter
const selectedOptionIds = ref([]);

function toggleOption(optionId) {
  const poll = pollData.value?.poll;
  if (!poll) return;

  if (poll.allow_multiple_choices) {
    const idx = selectedOptionIds.value.indexOf(optionId);
    if (idx === -1) {
      selectedOptionIds.value.push(optionId);
    } else {
      selectedOptionIds.value.splice(idx, 1);
    }
  } else {
    selectedOptionIds.value = [optionId];
  }
}

// Valeurs calculées
const poll = computed(() => pollData.value?.poll ?? null);
const isAuthenticated = computed(() => pollData.value?.is_authenticated ?? false);
const userVoteOptionIds = computed(() => pollData.value?.user_vote_option_ids ?? []);
const hasVoted = computed(() => userVoteOptionIds.value.length > 0);

const isExpired = computed(() => {
  if (!poll.value?.ends_at) return false;
  return new Date(poll.value.ends_at) < new Date();
});

const canVote = computed(() => {
  return isAuthenticated.value && poll.value && !poll.value.is_draft && !isExpired.value;
});

const voteError = ref('');
const voteSuccess = ref(false);

async function submitVote() {
  if (selectedOptionIds.value.length === 0) {
    voteError.value = 'Veuillez sélectionner au moins une option.';
    return;
  }

  voteError.value = '';
  try {
    await fetchApi({
      url: `/polls/${props.token}/vote`,
      data: { option_ids: selectedOptionIds.value },
    });
    voteSuccess.value = true;
    const newData = await fetchApi({ url: `/polls/${props.token}` });
    pollData.value = newData;
    await loadResults();
  } catch (err) {
    voteError.value = err?.data?.message || 'Une erreur est survenue.';
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleString('fr-CH');
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-900 dark:text-white p-4">
    <div class="max-w-lg mx-auto py-8">

      <div v-if="pollError" class="text-center">
        <p class="text-red-600 text-lg">Sondage introuvable.</p>
      </div>

      <div v-else-if="!pollData" class="text-center text-slate-500">
        Chargement...
      </div>

      <div v-else>
        <div class="mb-6">
          <h1 class="text-2xl font-bold mb-1">{{ poll?.title || poll?.question }}</h1>
          <p v-if="poll?.title" class="text-slate-600 dark:text-slate-400">{{ poll?.question }}</p>

          <div class="flex flex-wrap gap-2 mt-2">
            <span v-if="poll?.is_draft" class="text-xs bg-slate-200 text-slate-700 px-2 py-0.5 rounded-full">Brouillon</span>
            <span v-else-if="isExpired" class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Terminé</span>
            <span v-else class="text-xs bg-teal-100 text-teal-800 px-2 py-0.5 rounded-full">En cours</span>
            <span v-if="poll?.allow_multiple_choices" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Choix multiples</span>
          </div>

          <p v-if="poll?.ends_at" class="text-xs text-slate-500 mt-2">
            <span v-if="isExpired"> Sondage terminé le {{ formatDate(poll.ends_at) }}</span>
            <span v-else>⏱ Se termine le {{ formatDate(poll.ends_at) }}</span>
          </p>
        </div>

        <div v-if="poll?.is_draft" class="bg-slate-100 dark:bg-slate-800 border rounded p-4 mb-6 text-sm text-slate-600 dark:text-slate-400">
          Ce sondage n'est pas encore lancé.
        </div>

        <div v-else class="mb-6">
          <div v-if="hasVoted" class="mb-4 bg-teal-50 dark:bg-teal-900/30 border border-teal-200 dark:border-teal-800 rounded p-3 text-sm text-teal-700 dark:text-teal-300">
            Vous avez déjà voté.
          </div>

          <div v-if="!isAuthenticated" class="mb-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded p-3 text-sm">
            <p>Vous devez être connecté pour voter.</p>
            <a v-if="loginUrl" :href="loginUrl" class="text-teal-600 hover:underline font-medium">Se connecter</a>
          </div>

          <div v-if="!isExpired">
            <h2 class="font-semibold mb-3 text-sm">
              {{ poll?.allow_multiple_choices ? 'Choisissez une ou plusieurs options :' : 'Choisissez une option :' }}
            </h2>

            <div class="space-y-2 mb-4">
              <button
                v-for="option in poll?.options"
                :key="option.id"
                @click="canVote ? toggleOption(option.id) : null"
                :disabled="!canVote"
                class="w-full text-left px-4 py-3 border rounded-lg text-sm transition-colors"
                :class="{
                  'border-teal-500 bg-teal-50 dark:bg-teal-900/30': selectedOptionIds.includes(option.id) || userVoteOptionIds.includes(option.id),
                  'hover:border-teal-400 cursor-pointer': canVote,
                  'opacity-60 cursor-default': !canVote,
                  'dark:bg-slate-800 dark:border-slate-600': !selectedOptionIds.includes(option.id) && !userVoteOptionIds.includes(option.id),
                }"
              >
                <span class="mr-2">
                  {{ (selectedOptionIds.includes(option.id) || userVoteOptionIds.includes(option.id)) ? '✓' : '○' }}
                </span>
                {{ option.label }}
              </button>
            </div>

            <p v-if="voteError" class="text-red-600 text-sm mb-3">{{ voteError }}</p>
            <p v-if="voteSuccess" class="text-teal-600 text-sm mb-3">Vote enregistré !</p>

            <button
              v-if="canVote"
              @click="submitVote"
              :disabled="selectedOptionIds.length === 0"
              class="w-full bg-teal-600 text-white py-3 rounded-lg hover:bg-teal-700 disabled:opacity-40 disabled:cursor-not-allowed font-medium"
            >
              Voter
            </button>
          </div>

          <div v-else class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded p-4 text-sm text-red-700 dark:text-red-300">
            Il n'est plus possible de voter, ce sondage est terminé.
          </div>
        </div>

        <div class="border rounded-lg p-4 bg-white dark:bg-slate-800 dark:border-slate-700">
          <h2 class="font-semibold mb-3">Résultats</h2>
          <div v-if="resultsError">
            <p class="text-sm text-slate-500">
              {{ resultsError?.status === 403 ? 'Les résultats de ce sondage sont privés.' : 'Impossible de charger les résultats.' }}
            </p>
          </div>
          <PollResults v-else :results="results" />
        </div>
      </div>
    </div>
  </div>
</template>