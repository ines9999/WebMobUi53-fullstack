<script setup>
/**
 * PollForm.vue
 * -------------
 * Formulaire de création ou d'édition d'un sondage.
 * Si la prop "poll" est fournie → mode édition (pré-rempli).
 * Sinon → mode création (formulaire vide).
 *
 * Ce composant émet deux événements :
 *  - "submit" avec les données du formulaire → le parent fait l'appel API
 *  - "cancel" → le parent ferme le formulaire
 */
import { ref, watch } from 'vue';

const props = defineProps({
  poll: { type: Object, default: null },
});

const emit = defineEmits(['submit', 'cancel']);

function initForm() {
  return {
    title: props.poll?.title ?? '',
    question: props.poll?.question ?? '',
    allow_multiple_choices: props.poll?.allow_multiple_choices ?? false,
    results_public: props.poll?.results_public ?? false,
    // La durée est en secondes dans la DB, on affiche en minutes dans l'UI
    duration: props.poll?.duration ? Math.floor(props.poll.duration / 60) : '',
    start_now: false,
    options: props.poll?.options?.map(o => o.label) ?? ['', ''],
  };
}

const form = ref(initForm());

// Si on ouvre un autre sondage à modifier, on réinitialise le formulaire
watch(() => props.poll, () => {
  form.value = initForm();
});

function addOption() {
  form.value.options.push('');
}

function removeOption(index) {
  if (form.value.options.length > 2) {
    form.value.options.splice(index, 1);
  }
}

function handleSubmit() {
  // Convertir les minutes en secondes avant d'envoyer
  const durationSeconds = form.value.duration
    ? parseInt(form.value.duration) * 60
    : null;

  emit('submit', {
    title: form.value.title || null,
    question: form.value.question,
    options: form.value.options.filter(o => o.trim() !== ''),
    allow_multiple_choices: form.value.allow_multiple_choices,
    results_public: form.value.results_public,
    duration: durationSeconds,
    start_now: form.value.start_now,
  });
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">

    <div>
      <label class="block text-sm font-medium mb-1">Titre (optionnel)</label>
      <input v-model="form.title" type="text" placeholder="Titre du sondage"
        class="w-full border rounded px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600" />
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Question <span class="text-red-500">*</span></label>
      <input v-model="form.question" type="text" placeholder="Votre question..." required
        class="w-full border rounded px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600" />
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Options <span class="text-red-500">*</span></label>
      <div v-for="(option, index) in form.options" :key="index" class="flex gap-2 mb-2">
        <input v-model="form.options[index]" type="text" :placeholder="`Option ${index + 1}`" required
          class="flex-1 border rounded px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600" />
        <button type="button" @click="removeOption(index)" :disabled="form.options.length <= 2"
          class="px-3 py-2 text-red-500 hover:text-red-700 disabled:opacity-30">✕</button>
      </div>
      <button type="button" @click="addOption" class="text-sm text-teal-600 hover:text-teal-800">
        + Ajouter une option
      </button>
    </div>

    <div class="space-y-2">
      <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" v-model="form.allow_multiple_choices" class="rounded" />
        Autoriser plusieurs choix
      </label>
      <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" v-model="form.results_public" class="rounded" />
        Résultats publics
      </label>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Durée (en minutes, optionnel)</label>
      <input v-model="form.duration" type="number" min="1" placeholder="Ex: 60"
        class="w-full border rounded px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600" />
    </div>

    <div v-if="!poll || poll.is_draft">
      <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" v-model="form.start_now" class="rounded" />
        Lancer le sondage maintenant
      </label>
    </div>

    <div class="flex gap-2 pt-2">
      <button type="submit" class="bg-teal-600 text-white px-4 py-2 rounded hover:bg-teal-700 text-sm">
        {{ poll ? 'Enregistrer' : 'Créer' }}
      </button>
      <button type="button" @click="emit('cancel')"
        class="border px-4 py-2 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-sm">
        Annuler
      </button>
    </div>
  </form>
</template>