<?php

namespace App\Repositories;

use Illuminate\Contracts\Auth\Guard;

use Illuminate\Support\Facades\DB;

use App\Models\Poll, App\Models\Answer, App\Models\User;

class PollRepository
{

	/**
	 * Instance de Guard.
	 *
	 * @var Guard
	 */
	protected $auth;

	/**
	 * Crée une nouvelle instance de PollRepository
	 *
	 * @param Illuminate\Contracts\Auth\Guard $auth
	 * @return void
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * Enregistrement des réponses
	 *
	 * @param  App\Http\Requests\PollUpdateRequest $request;
	 * @param  App\Models\Poll $poll
	 * @return void
	 */
	private function saveAnswers($request, $poll)
	{
		$answers = [];

		foreach ($request->input('answers') as $value) 
		{
			array_push($answers, new Answer(['answer' => $value]));
		}	

		$poll->answers()->saveMany($answers);
	}

	/**
	 * Récupération des sondages existants
	 *
	 * @param  integer $n
	 * @return array
	 */
	public function getPaginate($n)
	{
		// Ici on doit renvoyer un tableau avec les clés "polls" et "polls_voted"
		// La valeur de "polls" doit être l'ensemble des sondages avec une pagination définie par la variable $n
		// Les valeurs de "polls_voted" doivent être les questions pour lesquelles il y a eu des votes 
		// Il existe une méthode has d'Eloquent qui peut vous faciliter la tâche (http://laravel.com/docs/5.0/eloquent#querying-relations)
		
		$polls = Poll::all();

        $poll_voted =[];
        foreach ($polls as $vote){
            if ($this->checkPoll($vote->id)){
                array_push($poll_voted, $vote);
            }
        }
        $poll = Poll::paginate($n);
        return ['polls'=>$poll,'polls_voted'=>$poll_voted];

	}

	/**
	 * Enregistrement d'un sondage
	 *
	 * @param  App\Http\Requests\PollUpdateRequest $request;
	 * @return void
	 */
	public function store($request)
	{
		// Ici on doit enregistrer dans la base le nouveau sondage
		// Les informations du sondage peuvent être récupérées avec $request->input(...)
		// Il faut enregistrer la question dans la table "polls"
		// Il faut enregistrer toutes les réponses dans la tables "answers" mais Léo a déjà créé la fonction privée saveAnswers pour ça, il suffit donc de l'appeler
		$poll = new Poll(['question'=>$request->input('question')]);
        $poll->save();
	    $this->saveAnswers($request,$poll);
	}

	/**
	 * Récupération des informations d'un sondage pour affichage
	 *
	 * @return array
	 */
	public function getPollWithAnswersAndDone($id)
	{
		// Ici on doit récupérer toutes les informations pour afficher un sondage
		// On doit renvoyer un tableau avec les clés "poll", "total" et "done"
		// "poll" doit contenir les informations issues de la table "polls" pour le sondage avec l'identifiant $id et doit aussi charger les réponses (avec with)
		// "total" doit retourner le nombre total de résultats pour le calcul du pourcentage, il existe pour ça la méthode d'aggrégation "sum" (http://laravel.com/docs/5.0/queries#aggregates)
		// "done" doit contenir "true" si un vote a déjà eu lieu pour ce sondage, "false" dans le cas inverse, il y a dans le modèle User la méthode hasVoted qui vous donne cette information
		$poll = $this->getById($id);
	    $total = $poll->answers->sum('result');

        $done = $this->checkPoll($id);
        return ['poll'=>$poll,'total'=>$total,'done'=>$done];
	}

	/**
	 * Récupération des informations d'un sondage pour modification
	 *
	 * @param  integer $id
	 * @return mixed
	 */
	public function getPollWithAnswers($id)
	{
		// Ici on doit renvoyer un tableau avec les clé "poll" en chargeant aussi les réponses (avec with)
		// "poll" doit contenir les informations issues de la table "polls" pour le sondage avec l'identifiant $id
		return ['poll'=>$this->getById($id)];
	}

	/**
	 * Teste qu'un sondage a déjà reçu un vote
	 *
	 * @param  integer $id
	 * @return mixed
	 */
	public function checkPoll($id)
	{
		return $this->getById($id)->users()->count() != 0;
	}

	/**
	 * Mise à jour sondage suite à modification
	 *
	 * @param  App\Http\Requests\PollUpdateRequest $request;
	 * @param  integer $id
	 * @return void
	 */
	public function update($request, $id)
	{
		// Ici on doit mettre à jour les tables "polls" et "answers" à partir du retour du formulaire de modification
		// Les informations du sondage peuvent être récupérées avec $request->input(...)
		// Il faut enregistrer la question dans la table "polls"
		// Il faut enregistrer toutes les réponses dans la tables "answers" (Attention ! Il y a déjà des réponses pour ce sondage, il faut donc penser à les supprimer !)
		// Léo a déjà créé la fonction privée saveAnswers pour ça, il suffit donc de l'appeler
		$poll = $this->getById($id);
        $poll->question = $request->input('question');
        $poll->save();

        $answers = $poll->answers;
        foreach ($answers as $answer)
        {
            $answer->delete();
        }

        $this->saveAnswers($request,$poll);
	}

	/**
	 * Suppression d'un sondage
	 *
	 * @param  integer $id
	 * @return void
	 */
	public function destroy($id)
	{
		// Ici on doit supprimer le sondage d'identifiant $id
		// Il faut nettoyer les tables "polls", "answers" et "poll_user" (pour cette dernière pensez à la méthode "detach" qui simplifie la syntaxe)
		$poll = $this->getById($id);
        $poll->delete();

        $answers = $poll->answers;
        foreach ($answers as $answer)
        {
            $answer->delete();
        }

        $poll_users = DB::table('poll_user')
            ->where('poll_id', '=', $id)
            ->get();

        foreach ($poll_users as $poll_user){
            $poll_user->delete();
        }
	}

	/**
	 * Récupération sondage par son id
	 *
	 * @param  integer $id
	 * @return void
	 */
	public function getById($id)
	{
		return Poll::find($id);
	}

	/**
	 * Mise à jour d'un vote
	 *
	 * @param  integer $id
	 * @param  integer $option_id
	 * @param  App\Models\User $user
	 * @return void
	 */
	public function saveVote($id, $option_id, $user)
	{
		// Mise à jour du résultat pour la réponse
		Answer::whereId($option_id)->increment('result');

		// Mise à jour du vote pour l'utilisateur
		$user->polls()->attach($id);
	}

}