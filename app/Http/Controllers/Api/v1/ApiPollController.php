<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiPollController extends Controller
{
    /**
     * Liste des sondages de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $polls = $request->user()
            ->polls()
            ->withCount('votes')
            ->orderBy('created_at', 'desc')
            ->get();

        return $polls;
    }

    /**
     * Créer un nouveau sondage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question'               => 'required|string|max:255',
            'title'                  => 'nullable|string|max:255',
            'options'                => 'required|array|min:2',
            'options.*'              => 'required|string|max:255',
            'allow_multiple_choices' => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
            'start_now'              => 'boolean',
        ]);

        $startNow = $validated['start_now'] ?? false;
        $duration = $validated['duration'] ?? null;

        $poll = $request->user()->polls()->create([
            'question'               => $validated['question'],
            'title'                  => $validated['title'] ?? null,
            'secret_token'           => Str::random(32),
            'is_draft'               => !$startNow,
            'allow_multiple_choices' => $validated['allow_multiple_choices'] ?? false,
            'results_public'         => $validated['results_public'] ?? false,
            'duration'               => $duration,
            'started_at'             => $startNow ? now() : null,
            'ends_at'                => ($startNow && $duration) ? now()->addSeconds($duration) : null,
        ]);

        foreach ($validated['options'] as $label) {
            $poll->options()->create(['label' => $label]);
        }

        return response()->json($poll->load('options'), 201);
    }

    /**
     * Afficher un sondage via son token (public ou authentifié).
     */
    public function show(Request $request, string $token)
    {
        $poll = Poll::with(['options' => function ($query) {
            $query->withCount('votes');
        }, 'user'])->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $user = $request->user();
        $isOwner = $user && $user->id === $poll->user_id;

        $userVoteOptionIds = [];
        if ($user) {
            $userVoteOptionIds = PollVote::where('poll_id', $poll->id)
                ->where('user_id', $user->id)
                ->pluck('poll_option_id')
                ->toArray();
        }

        return response()->json([
            'poll'                => $poll,
            'is_owner'            => $isOwner,
            'user_vote_option_ids' => $userVoteOptionIds,
            'is_authenticated'    => (bool) $user,
        ]);
    }

    /**
     * Mettre à jour un sondage.
     */
    public function update(Request $request, Poll $poll)
    {
        if ($request->user()->id !== $poll->user_id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $validated = $request->validate([
            'question'               => 'sometimes|required|string|max:255',
            'title'                  => 'nullable|string|max:255',
            'options'                => 'sometimes|required|array|min:2',
            'options.*'              => 'required|string|max:255',
            'allow_multiple_choices' => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
            'start_now'              => 'boolean',
        ]);

        $poll->fill([
            'question'               => $validated['question'] ?? $poll->question,
            'title'                  => array_key_exists('title', $validated) ? $validated['title'] : $poll->title,
            'allow_multiple_choices' => $validated['allow_multiple_choices'] ?? $poll->allow_multiple_choices,
            'results_public'         => $validated['results_public'] ?? $poll->results_public,
            'duration'               => array_key_exists('duration', $validated) ? $validated['duration'] : $poll->duration,
        ]);

        if (!empty($validated['start_now']) && $poll->is_draft) {
            $poll->is_draft = false;
            $poll->started_at = now();
            if ($poll->duration) {
                $poll->ends_at = now()->addSeconds($poll->duration);
            }
        }

        $poll->save();

        if (isset($validated['options'])) {
            $poll->options()->delete();
            foreach ($validated['options'] as $label) {
                $poll->options()->create(['label' => $label]);
            }
        }

        return response()->json($poll->load('options'));
    }

    /**
     * Supprimer un sondage.
     */
    public function destroy(Request $request, Poll $poll)
    {
        if ($request->user()->id !== $poll->user_id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $poll->delete();

        return response()->json(['message' => 'Sondage supprimé.']);
    }

    /**
     * Voter pour un sondage.
     */
    public function vote(Request $request, string $token)
    {
        $poll = Poll::with('options')->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        if ($poll->is_draft) {
            return response()->json(['message' => 'Ce sondage n\'est pas encore lancé.'], 422);
        }

        if ($poll->ends_at && now()->isAfter($poll->ends_at)) {
            return response()->json(['message' => 'Ce sondage est terminé.'], 422);
        }

        $validated = $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => 'required|integer|exists:poll_options,id',
        ]);

        $optionIds = $validated['option_ids'];
        $validIds = $poll->options->pluck('id')->toArray();

        foreach ($optionIds as $id) {
            if (!in_array($id, $validIds)) {
                return response()->json(['message' => 'Option invalide.'], 422);
            }
        }

        if (!$poll->allow_multiple_choices && count($optionIds) > 1) {
            return response()->json(['message' => 'Ce sondage n\'accepte qu\'un seul choix.'], 422);
        }

        $user = $request->user();

        PollVote::where('poll_id', $poll->id)->where('user_id', $user->id)->delete();

        foreach ($optionIds as $optionId) {
            PollVote::create([
                'poll_id'        => $poll->id,
                'user_id'        => $user->id,
                'poll_option_id' => $optionId,
            ]);
        }

        return response()->json(['message' => 'Vote enregistré.']);
    }

    /**
     * Résultats d'un sondage.
     */
    public function results(Request $request, string $token)
    {
        $poll = Poll::with(['options' => function ($query) {
            $query->withCount('votes');
        }])->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $user = $request->user();
        $isOwner = $user && $user->id === $poll->user_id;

        if (!$poll->results_public && !$isOwner) {
            return response()->json(['message' => 'Résultats non publics.'], 403);
        }

        $totalVotes = $poll->votes()->count();

        $options = $poll->options->map(function ($option) use ($totalVotes) {
            return [
                'id'          => $option->id,
                'label'       => $option->label,
                'votes_count' => $option->votes_count,
                'percentage'  => $totalVotes > 0
                    ? round(($option->votes_count / $totalVotes) * 100)
                    : 0,
            ];
        });

        return response()->json([
            'total_votes' => $totalVotes,
            'options'     => $options,
        ]);
    }
}