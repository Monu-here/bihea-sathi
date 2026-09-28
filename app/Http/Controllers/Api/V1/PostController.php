<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PostCommentModel;
use App\Models\PostLikeModel;
use App\Models\PostModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{

    public function createPost(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'caption' => 'nullable|string|max:1000',
            'media' => 'required|file|mimes:jpg,jpeg,png,gif,mp4,mov,avi|max:51200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->user()->id;
        $file = $request->file('media');

        $extension = $file->getClientOriginalExtension();
        $mediaType = in_array(strtolower($extension), ['mp4', 'mov', 'avi']) ? 'video' : 'image';

        $fileName = uniqid('post_') . '_' . time() . '.' . $extension;
        $destinationPath = public_path('uploads/posts');

        if (! file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file->move($destinationPath, $fileName);

        $post = PostModel::query()->create([
            'user_id' => $userId,
            'caption' => $request->input('caption'),
            'media_path' => '/uploads/posts/' . $fileName,
            'media_type' => $mediaType,
        ]);

        Log::info('Post created', ['post_id' => $post->id, 'user_id' => $userId]);

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => $post,
        ], 201);
    }


    public function getConnectionsPosts(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $acceptedConnectionIds = DB::table('connection_models')
            ->where('status', 'accepted')
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)
                    ->orWhere('to_user_id', $userId);
            })
            ->select(DB::raw("CASE WHEN from_user_id = {$userId} THEN to_user_id ELSE from_user_id END as connected_user_id"))
            ->pluck('connected_user_id');

        $posts = PostModel::query()
            ->whereIn('user_id', $acceptedConnectionIds)
            ->with(['user:id,name,email'])
            ->with(['userProfile:id,user_id,main_photo'])
            ->withCount(['likes', 'comments']) 
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info('Connections posts retrieved', ['user_id' => $userId, 'count' => $posts->count()]);

        return response()->json([
            'success' => true,
            'message' => 'Posts from connections retrieved successfully',
            'data' => $posts,
        ]);
    }


    public function getMyPosts(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $posts = PostModel::query()
            ->where('user_id', $userId)
            ->with(['user:id,name,email', 'userProfile:id,user_id,main_photo'])
            ->withCount(['likes', 'comments'])   
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'My posts retrieved successfully',
            'data' => $posts,
        ]);
    }


    public function deletePost(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $post = PostModel::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found or you do not have permission to delete it',
            ], 404);
        }

        $mediaPath = public_path($post->media_path);
        if (file_exists($mediaPath)) {
            unlink($mediaPath);
        }

        $post->delete();

        Log::info('Post deleted', ['post_id' => $id, 'user_id' => $userId]);

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully',
        ]);
    }


    public function getPost(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $post = PostModel::query()
            ->with(['user:id,name,email'])
            ->with(['userProfile:id,user_id,main_photo'])
            ->find($id);

        if (! $post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
        }

        if ($post->user_id === $userId) {
            return response()->json([
                'success' => true,
                'data' => $post,
            ]);
        }

        $isConnected = DB::table('connection_models')
            ->where('status', 'accepted')
            ->where(function ($q) use ($userId, $post) {
                $q->where(function ($inner) use ($userId, $post) {
                    $inner->where('from_user_id', $userId)
                        ->where('to_user_id', $post->user_id);
                })->orWhere(function ($inner) use ($userId, $post) {
                    $inner->where('from_user_id', $post->user_id)
                        ->where('to_user_id', $userId);
                });
            })
            ->exists();

        if (! $isConnected) {
            return response()->json([
                'success' => false,
                'message' => 'You are not connected to view this post',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $post,
        ]);
    }


    private function canInteractWithPost(int $userId, PostModel $post): bool
    {
        if ($post->user_id === $userId) {
            return true;
        }

        return DB::table('connection_models')
            ->where('status', 'accepted')
            ->where(function ($q) use ($userId, $post) {
                $q->where(function ($inner) use ($userId, $post) {
                    $inner->where('from_user_id', $userId)
                        ->where('to_user_id', $post->user_id);
                })->orWhere(function ($inner) use ($userId, $post) {
                    $inner->where('from_user_id', $post->user_id)
                        ->where('to_user_id', $userId);
                });
            })
            ->exists();
    }


    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $post = PostModel::query()->find($id);

        if (! $post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
        }

        if (! $this->canInteractWithPost($userId, $post)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not connected to like this post',
            ], 403);
        }

        $existingLike = PostLikeModel::query()
            ->where('post_id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($existingLike) {
            $existingLike->delete();
            $liked = false;
            $message = 'Post unliked successfully';
        } else {
            PostLikeModel::query()->create([
                'post_id' => $id,
                'user_id' => $userId,
            ]);
            $liked = true;
            $message = 'Post liked successfully';
        }

        $likesCount = PostLikeModel::query()->where('post_id', $id)->count();

        Log::info('Post like toggled', ['post_id' => $id, 'user_id' => $userId, 'liked' => $liked]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'liked' => $liked,
                'likes_count' => $likesCount,
            ],
        ]);
    }


    public function addComment(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->user()->id;

        $post = PostModel::query()->find($id);

        if (! $post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
        }

        if (! $this->canInteractWithPost($userId, $post)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not connected to comment on this post',
            ], 403);
        }

        $comment = PostCommentModel::query()->create([
            'post_id' => $id,
            'user_id' => $userId,
            'content' => $request->input('content'),
        ]);

        $comment->load('user:id,name,email');
        $comment->load('userProfile:id,user_id,main_photo');
        Log::info('Comment added', ['post_id' => $id, 'comment_id' => $comment->id, 'user_id' => $userId]);

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'data' => $comment,
        ], 201);
    }


    public function getComments(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $post = PostModel::query()->find($id);

        if (! $post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
        }

        if (! $this->canInteractWithPost($userId, $post)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not connected to view comments on this post',
            ], 403);
        }

        $comments = PostCommentModel::query()
            ->where('post_id', $id)
            ->with('user:id,name,email', 'userProfile:id,user_id,main_photo')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }


    public function deleteComment(Request $request, int $postId, int $commentId): JsonResponse
    {
        $userId = $request->user()->id;

        $comment = PostCommentModel::query()
            ->where('id', $commentId)
            ->where('post_id', $postId)
            ->where('user_id', $userId)
            ->first();

        if (! $comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found or you do not have permission to delete it',
            ], 404);
        }

        $comment->delete();

        Log::info('Comment deleted', ['comment_id' => $commentId, 'post_id' => $postId, 'user_id' => $userId]);

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully',
        ]);
    }


    public function getLikes(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $post = PostModel::query()->find($id);

        if (! $post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
        }

        if (! $this->canInteractWithPost($userId, $post)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not connected to view likes on this post',
            ], 403);
        }

        $likes = PostLikeModel::query()
            ->where('post_id', $id)
            ->with('user:id,name,email', 'userProfile:id,user_id,main_photo')
            ->get();

        $userHasLiked = PostLikeModel::query()
            ->where('post_id', $id)
            ->where('user_id', $userId)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'likes' => $likes,
                'likes_count' => $likes->count(),
                'user_has_liked' => $userHasLiked,
            ],
        ]);
    }
}
