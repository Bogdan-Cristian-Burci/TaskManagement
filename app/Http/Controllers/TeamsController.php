<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignUsersToTeamRequest;
use App\Http\Requests\DetachUserFromTeamRequest;
use App\Http\Requests\TeamsRequest;
use App\Http\Resources\TeamsResource;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TeamsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     *
     * Show all teams for logged user
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $user=auth()->user();
        return TeamsResource::collection($user->teams);
    }

    /**
     * Add a new team
     *
     * @param TeamsRequest $request
     * @return TeamsResource
     */
    public function store(TeamsRequest $request)
    {
        $team = Team::create($request->validated());
        $user = auth()->user();
        $user->teams()->attach($team->id);
        return new TeamsResource($team);
    }

    public function show(Team $team)
    {
        $user = auth()->user();
        if (!$user->teams->contains($team)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return new TeamsResource($team);
    }

    public function update(TeamsRequest $request, Team $team)
    {
        $user = auth()->user();

        if ($user->id !== $team->team_lead_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $team->update($request->validated());

        return new TeamsResource($team);
    }

    public function attachUsers(AssignUsersToTeamRequest $request, Team $team)
    {
        $userIds = $request->validated()['user_ids'];
        $team->users()->attach($userIds);

        return response()->json(['message' => 'Users assigned successfully'], 200);
    }

    public function detachUser(DetachUserFromTeamRequest $request, Team $team)
    {
        $userIds = $request->validated()['user_ids'];

        if (empty($userIds)) {
            return response()->json(['message' => 'No valid users to detach'], 400);
        }

        $team->users()->detach($userIds);

        return response()->json(['message' => 'Users detached successfully'], 200);
    }
    public function destroy(Team $team)
    {
        $team->delete();

        return response()->json();
    }
}
