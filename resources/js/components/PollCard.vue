<script setup>
/**
 * PollCard.vue
 * -------------
 * Affiche les infos d'un sondage sous forme de carte dans le dashboard.
 * Ce composant ne fait rien tout seul : il émet des événements
 * que le parent (AppPollDashboardIntegrated) va traiter.
 *
 * Événements émis :
 *  - "edit"      → l'utilisateur veut modifier ce sondage
 *  - "delete"    → l'utilisateur veut supprimer ce sondage
 *  - "launch"    → l'utilisateur veut lancer ce sondage (brouillon → actif)
 *  - "copy-link" → l'utilisateur a copié le lien de partage
 */
const props = defineProps({
  poll: { type: Object, required: true },
});

const emit = defineEmits(['edit', 'delete', 'launch', 'copy-link']);

function formatDate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleString('fr-CH');
}

function shareLink() {
  return `${window.location.origin}/polls/${props.poll.secret_token}`;
}

function copyLink() {
  navigator.clipboard.writeText(shareLink());
  emit('copy-link');
}
</script>

<template>
  <div class="border rounded-lg p-4 bg-white dark:bg-slate-800 dark:border-slate-700 shadow-sm">

    <div class="flex items-start justify-between gap-2 mb-2">
      <div>
        <h3 class="font-semibold text-sm">{{ poll.title || poll.question }}</h3>
        <p v-if="poll.title" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ poll.question }}</p>
      </div>
      <span
        class="shrink-0 text-xs px-2 py-0.5 rounded-full font-medium"
        :class="poll.is_draft
          ? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
          : 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200'"
      >
        {{ poll.is_draft ? 'Brouillon' : 'Lancé' }}
      </span>
    </div>

    <div class="text-xs text-slate-500 dark:text-slate-400 space-y-0.5 mb-3">
      <p>{{ poll.votes_count ?? 0 }} vote(s) · {{ poll.results_public ? 'Résultats publics' : 'Résultats privés' }}</p>
      <p v-if="poll.allow_multiple_choices">Choix multiples autorisés</p>
      <p v-if="poll.ends_at">Fin : {{ formatDate(poll.ends_at) }}</p>
    </div>

    <div class="flex flex-wrap gap-2">
      <button @click="copyLink" class="text-xs border px-3 py-1 rounded hover:bg-slate-50 dark:hover:bg-slate-700">
        Copier le lien
      </button>
      <button v-if="poll.is_draft" @click="emit('launch', poll)" class="text-xs bg-teal-600 text-white px-3 py-1 rounded hover:bg-teal-700">
        Lancer
      </button>
      <button @click="emit('edit', poll)" class="text-xs border px-3 py-1 rounded hover:bg-slate-50 dark:hover:bg-slate-700">
        Modifier
      </button>
      <button @click="emit('delete', poll)" class="text-xs text-red-500 border border-red-200 px-3 py-1 rounded hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-900/20">
        Supprimer
      </button>
    </div>
  </div>
</template>