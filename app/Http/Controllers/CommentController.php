<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;


class CommentController extends Controller
{
    public function index($documentId)
    {
        $comments = Comment::with(['user', 'replies.user'])
            ->where('document_id', $documentId)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'document_id' => 'required',
            'text' => 'required|string',
        ]);

        $comment = Comment::create([
            'document_id' => $request->document_id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'text' => $request->text,
        ]);

        return response()->json($comment->load('user'));
    }

}
