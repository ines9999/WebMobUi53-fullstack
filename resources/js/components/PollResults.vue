<script setup>
/**
 * PollResults.vue
 * ----------------
 * Affiche les résultats d'un sondage sous forme de graphique à barres.
 * Reçoit les données via props, mises à jour toutes les 5s par le parent.
 */
const props = defineProps({
  results: { type: Object, default: null },
});

// Couleurs pour les barres (on cycle si plus de 6 options)
const colors = ['bg-teal-500', 'bg-blue-500', 'bg-purple-500', 'bg-orange-500', 'bg-pink-500', 'bg-yellow-500'];
</script>

<template>
  <div v-if="!results">
    <p class="text-sm text-slate-500">Chargement des résultats...</p>
  </div>

  <div v-else>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">
      {{ results.total_votes }} vote(s) au total
    </p>

    <div class="space-y-3">
      <div v-for="(option, index) in results.options" :key="option.id">
        <div class="flex justify-between text-sm mb-1">
          <span>{{ option.label }}</span>
          <span class="font-medium">{{ option.votes_count }} ({{ option.percentage }}%)</span>
        </div>
        <!-- Barre dont la largeur = pourcentage des votes -->
        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-4 overflow-hidden">
          <div
            class="h-4 rounded-full transition-all duration-500"
            :class="colors[index % colors.length]"
            :style="{ width: option.percentage + '%' }"
          ></div>
        </div>
      </div>
    </div>
  </div>
</template>