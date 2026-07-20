<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ConnectionRequestController extends Controller
{
    public function sendConnectionRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_user_id' => 'required|integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }
        $fromUserId = $request->user()->id;
        $toUserId = $request->input('to_user_id');
        if ($fromUserId == $toUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot send a connection request to yourself',
            ], 400);
        }
        $existingRequest = DB::table('connection_models')
            ->where(function ($q) use ($fromUserId, $toUserId) {
                $q->where('from_user_id', $fromUserId)->where('to_user_id', $toUserId);
            })
            ->orWhere(function ($q) use ($fromUserId, $toUserId) {
                $q->where('from_user_id', $toUserId)->where('to_user_id', $fromUserId);
            })
            ->first();
        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'A connection request already exists between you and this user',
            ], 400);
        }
        DB::table('connection_models')->insert([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Connection request sent successfully',
        ]);
    }

    public function getConnectionRequests(Request $request)
    {
        $userId = $request->user()->id;
        $connectionRequests = DB::table('connection_models')
            ->where('to_user_id', $userId)
            ->where('status', 'pending')
            ->join('users', 'connection_models.from_user_id', '=', 'users.id')
            ->select('connection_models.id as request_id', 'users.id as from_user_id', 'users.name as from_user_name', 'users.email as from_user_email', 'connection_models.created_at')
            ->get();
        Log::info('Connection Requests for User ID: ' . $userId, ['requests' => $connectionRequests]);

        return response()->json([
            'success' => true,
            'data' => $connectionRequests,
        ]);
    }

    public function respondConnectionRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_user_id' => 'required|integer|exists:users,id',
            'action' => 'required|in:accept,reject',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }
        $toUserId = $request->user()->id;
        $fromUserId = $request->input('from_user_id');
        $action = $request->input('action');
        $connectionRequest = DB::table('connection_models')
            ->where('from_user_id', $fromUserId)
            ->where('to_user_id', $toUserId)
            ->first();
        if (! $connectionRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Connection request not found',
            ], 404);
        }
        if ($connectionRequest->status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This connection request has already been responded to',
            ], 400);
        }
        DB::table('connection_models')
            ->where('id', $connectionRequest->id)
            ->update(['status' => $action == 'accept' ? 'accepted' : 'rejected', 'updated_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Connection request has been {$action}ed successfully",
        ]);
    }

    public function showOtherUserProfileDetails(Request $request, $id)
    {
        $profileDetails = DB::table('profile_models')
            ->where('user_id', $id)
            ->join('users', 'profile_models.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'users.name', 'users.email', 'profile_models.*')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $profileDetails,
        ]);
    }


    public function getMyConnections(Request $request)
    {
        $userId = $request->user()->id;

        $connections = DB::table('connection_models')
            ->where('status', 'accepted')
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)
                    ->orWhere('to_user_id', $userId);
            })
            ->select(DB::raw("CASE WHEN from_user_id = {$userId} THEN to_user_id ELSE from_user_id END as connected_user_id"))
            ->distinct();

        $result = DB::table('users')
            ->joinSub($connections, 'conn', 'users.id', '=', 'conn.connected_user_id')
            ->join('profile_models', 'users.id', '=', 'profile_models.user_id')
            ->select('users.id as user_id', 'users.name', 'users.email', 'profile_models.*')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
