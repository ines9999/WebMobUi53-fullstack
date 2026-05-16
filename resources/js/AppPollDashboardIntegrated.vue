<script setup>
/**
 * AppPollDashboardIntegrated.vue
 * --------------------------------
 * app principale du dashboard.
 * permet à l'utilisateur connecté de :
 *  - voir la liste de ses sondages
 *  - créer un nouveau sondage
 *  - modifier ou supprimer un sondage existant
 *  - lancer un sondage qui est encore en brouillon
 *  - copier le lien de partage
 */
import { ref, watch } from 'vue';
import { useFetchApi } from './composables/useFetchApi';
import PollCard from './components/PollCard.vue';
import PollForm from './components/PollForm.vue';

const props = defineProps({
  loginUrl: { type: String, default: null },
});

const { fetchApi, fetchApiToRef } = useFetchApi();

const { data: polls, error: pollsError, fetchNow: reloadPolls } = fetchApiToRef({ url: '/polls' });

watch(pollsError, err => {
  if (err?.status === 401 && props.loginUrl) {
    window.location.href = props.loginUrl;
  }
});

const showForm = ref(false);
const editingPoll = ref(null);
const notification = ref('');
const formError = ref('');

function openCreate() {
  editingPoll.value = null;
  formError.value = '';
  showForm.value = true;
}

function openEdit(poll) {
  editingPoll.value = poll;
  formError.value = '';
  showForm.value = true;
}

function closeForm() {
  showForm.value = false;
  editingPoll.value = null;
  formError.value = '';
}

function notify(msg) {
  notification.value = msg;
  setTimeout(() => (notification.value = ''), 3000);
}

async function handleFormSubmit(formData) {
  formError.value = '';
  try {
    if (editingPoll.value) {
      await fetchApi({
        url: `/polls/${editingPoll.value.id}`,
        method: 'PUT',
        data: formData,
      });
      notify('Sondage modifié !');
    } else {
      await fetchApi({ url: '/polls', data: formData });
      notify('Sondage créé !');
    }
    closeForm();
    reloadPolls();
  } catch (err) {
    formError.value = err?.data?.message || 'Une erreur est survenue.';
  }
}

async function launchPoll(poll) {
  try {
    await fetchApi({
      url: `/polls/${poll.id}`,
      method: 'PUT',
      data: { start_now: true },
    });
    notify('Sondage lancé !');
    reloadPolls();
  } catch (err) {
    alert(err?.data?.message || 'Erreur lors du lancement.');
  }
}

async function deletePoll(poll) {
  if (!confirm(`Supprimer "${poll.title || poll.question}" ?`)) return;
  try {
    await fetchApi({ url: `/polls/${poll.id}`, method: 'DELETE' });
    notify('Sondage supprimé.');
    reloadPolls();
  } catch (err) {
    alert(err?.data?.message || 'Erreur lors de la suppression.');
  }
}

function onCopyLink() {
  notify('Lien copié !');
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-bold">Mes sondages</h1>
      <button @click="openCreate" class="bg-teal-600 text-white px-4 py-2 rounded hover:bg-teal-700 text-sm">
        + Nouveau sondage
      </button>
    </div>

    <div v-if="notification" class="mb-4 bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200 px-4 py-2 rounded text-sm">
      {{ notification }}
    </div>

    <div v-if="showForm" class="mb-6 border rounded-lg p-4 bg-white dark:bg-slate-800 dark:border-slate-700 shadow-sm">
      <h2 class="font-semibold mb-3">{{ editingPoll ? 'Modifier le sondage' : 'Nouveau sondage' }}</h2>
      <p v-if="formError" class="mb-3 text-red-600 text-sm">{{ formError }}</p>
      <PollForm :poll="editingPoll" @submit="handleFormSubmit" @cancel="closeForm" />
    </div>

    <div v-if="!polls" class="text-slate-500 text-sm">Chargement...</div>

    <div v-else-if="polls.length === 0" class="text-slate-500 text-sm">
      Vous n'avez pas encore de sondage.
    </div>

    <div v-else class="space-y-4">
      <PollCard
        v-for="poll in polls"
        :key="poll.id"
        :poll="poll"
        @edit="openEdit"
        @delete="deletePoll"
        @launch="launchPoll"
        @copy-link="onCopyLink"
      />
    </div>
  </div>
</template>