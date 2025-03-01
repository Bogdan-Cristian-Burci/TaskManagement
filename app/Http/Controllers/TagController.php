<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TagController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        return TagResource::collection(Tag::all());
    }

    public function store(TagRequest $request)
    {
        return new TagResource(Tag::create($request->validated()));
    }

    public function show(Tag $tag)
    {
        return new TagResource($tag);
    }

    public function update(TagRequest $request, Tag $tag)
    {
        $tag->update($request->validated());

        return new TagResource($tag);
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return response()->json();
    }
}
