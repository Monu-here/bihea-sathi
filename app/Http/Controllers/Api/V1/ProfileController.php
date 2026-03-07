<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\WelcomeMail;
use App\Mail\EmailVerificationMail;
use App\Models\ProfileModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProfileController extends Controller
{

    public function signup(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'profile_for' => 'nullable|string|max:255',
                'full_name' => 'required|string|max:255',
                'gender' => 'nullable|integer|in:0,1,2',
                'date_of_birth' => 'nullable|date|before:today',
                'education' => 'nullable|string|max:255',
                'profession' => 'nullable|string|max:255',
                'annual_income' => 'nullable|string|max:255',
                'company_name' => 'nullable|string|max:255',
                'father_occupation' => 'nullable|string|max:255',
                'mother_occupation' => 'nullable|string|max:255',
                'siblings' => 'nullable|integer|min:0',
                'family_type' => 'nullable|string|max:255',
                'family_values' => 'nullable|string|max:255',
                'diet' => 'nullable|string|max:255',
                'drinking' => 'nullable|string|max:255',
                'smoking' => 'nullable|string|max:255',
                'partner_age_min' => 'nullable|integer|min:18',
                'partner_age_max' => 'nullable|integer|max:100',
                'partner_height_min' => 'nullable|string|max:255',
                'partner_height_max' => 'nullable|string|max:255',
                'main_photo' => 'nullable|string|max:255',
                'additional_photos' => 'nullable|array',
                'additional_photos.*' => 'string|max:255',
                'is_complete' => 'nullable|boolean',
                'is_verified' => 'nullable|boolean',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                ], 422);
            }
            DB::beginTransaction();
            $emailCheck = User::where('email', $request->email)->first();
            if ($emailCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already exists',
                ], 400);
            }
            $user = User::create([
                'name' => $request->full_name,
                'email' => $request->email,

                'password' => $request->password,
            ]);
            $verificationPin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $profile = ProfileModel::create([
                'user_id' => $user->id,
                'profile_for' => $request->profile_for,
                'full_name' => $request->full_name,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'education' => $request->education,
                'profession' => $request->profession,
                'annual_income' => $request->annual_income,
                'company_name' => $request->company_name,
                'father_occupation' => $request->father_occupation,
                'mother_occupation' => $request->mother_occupation,
                'siblings' => $request->siblings,
                'family_type' => $request->family_type,
                'family_values' => $request->family_values,
                'diet' => $request->diet,
                'drinking' => $request->drinking,
                'smoking' => $request->smoking,
                'partner_age_min' => $request->partner_age_min,
                'partner_age_max' => $request->partner_age_max,
                'partner_height_min' => $request->partner_height_min,
                'partner_height_max' => $request->partner_height_max,
                'partner_religion' => $request->partner_religion,
                'partner_locations' => $request->partner_locations,
                'main_photo' => $request->main_photo,
                'additional_photos' => $request->additional_photos ? json_encode($request->additional_photos) : null,
                'current_step' => 1,
                'is_complete' => $request->is_complete ?? false,
                'is_verified' => $request->is_verified ?? false,
                'verification_pin' => $verificationPin

            ]);
            Log::info('User registered successfully', ['user_id' => $user->id, 'email' => $user->email]);
            Log::info('data', ['profile' => $profile]);
            // Mail::to($user->email)->send(new WelcomeMail($user->name));

            Mail::to($user->email)->send(new EmailVerificationMail($user->name, $verificationPin));
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => $user,
                    'profile' => $profile,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|string|size:6',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }
        $profile = ProfileModel::whereHas('user', function ($query) use ($request) {
            $query->where('email', $request->email);
        })->first();
        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found for the provided email',
            ], 404);
        }
        if ($profile->verification_pin !== $request->pin) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification pin',
            ], 400);
        }
        User::where('id', $profile->user_id)->update(['email_verified_at' => now()]);
        $profile->is_verified = true;
        $profile->verification_pin = null;
        $profile->save();
        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'data' => [
                'profile' => $profile,
            ],
        ]);
    }

    public function resendVerification(Request $request)
    {
        $profile = ProfileModel::whereHas('user', function ($query) use ($request) {
            $query->where('email', $request->email);
        })->first();

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found'], 404);
        }

        // Generate new PIN and send email
        $pin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $profile->verification_pin = $pin;
        $profile->save();

        // Send verification email...
        Mail::to($profile->user->email)->send(new EmailVerificationMail($profile->user->name, $pin));
        return response()->json(['success' => true, 'message' => 'Verification code sent']);
    }

    public function myProfile(Request $request)
    {
        $profile = ProfileModel::where('user_id', $request->user()->id)->first();
        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => [
                'profile' => $profile,
            ],
        ]);
    }
    public function updateProfile(Request $request)
    {
        $profile = ProfileModel::where('user_id', $request->user()->id)->first();
        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found',
            ], 404);
        }
        $validator = Validator::make($request->all(), [
            'profile_for' => 'nullable|string|max:255',
            'full_name' => 'nullable|string|max:255',
            'gender' => 'nullable|integer|in:0,1,2',
            'date_of_birth' => 'nullable|date|before:today',
            'education' => 'nullable|string|max:255',
            'profession' => 'nullable|string|max:255',
            'annual_income' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'father_occupation' => 'nullable|string|max:255',
            'mother_occupation' => 'nullable|string|max:255',
            'siblings' => 'nullable|integer|min:0',
            'family_type' => 'nullable|string|max:255',
            'family_values' => 'nullable|string|max:255',
            'diet' => 'nullable|string|max:255',
            'drinking' => 'nullable|string|max:255',
            'smoking' => 'nullable|string|max:255',
            'partner_age_min' => 'nullable|integer|min:18',
            'partner_age_max' => 'nullable|integer|max:100',
            'partner_height_min' => 'nullable|string|max:255',
            'partner_height_max' => 'nullable|string|max:255',
            'partner_religion' => 'nullable|array',
            'partner_religion.*' => 'string|max:255',
            'partner_locations' => 'nullable|array',
            'partner_locations.*' => 'string|max:255',
            'main_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif',
            'additional_photos' => 'nullable|array',
            'additional_photos.*' => 'image|mimes:jpeg,png,jpg,gif',
            'is_complete' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }
        $imageFilePath  = '';
        if ($request->hasFile('main_photo')) {
            $image = $request->file('main_photo');
            $imageFilePath = 'profiles/' . uniqid() . '_' . time() . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public', $imageFilePath);
        }
        $additionalPhotoPaths = [];
        if ($request->hasFile('additional_photos')) {
            foreach ($request->file('additional_photos') as $photo) {
                $path = 'profiles/' . uniqid() . '_' . time() . '.' . $photo->getClientOriginalExtension();
                $photo->storeAs('public', $path);
                $additionalPhotoPaths[] = $path;
            }
        }

        $profile->update([
            'profile_for' => $request->profile_for ?? $profile->profile_for,
            'full_name' => $request->full_name ?? $profile->full_name,
            'gender' => $request->gender ?? $profile->gender,
            'date_of_birth' => $request->date_of_birth ?? $profile->date_of_birth,
            'education' => $request->education ?? $profile->education,
            'profession' => $request->profession ?? $profile->profession,
            'annual_income' => $request->annual_income ?? $profile->annual_income,
            'company_name' => $request->company_name ?? $profile->company_name,
            'father_occupation' => $request->father_occupation ?? $profile->father_occupation,
            'mother_occupation' => $request->mother_occupation ?? $profile->mother_occupation,
            'siblings' => $request->siblings ?? $profile->siblings,
            'family_type' => $request->family_type ?? $profile->family_type,
            'family_values' => $request->family_values ?? $profile->family_values,
            'diet' => $request->diet ?? $profile->diet,
            'drinking' => $request->drinking ?? $profile->drinking,
            'smoking' => $request->smoking ?? $profile->smoking,
            'partner_age_min' => $request->partner_age_min ?? $profile->partner_age_min,
            'partner_age_max' => $request->partner_age_max ?? $profile->partner_age_max,
            'partner_height_min' => $request->partner_height_min ?? $profile->partner_height_min,
            'partner_height_max' => $request->partner_height_max ?? $profile->partner_height_max,
            'partner_religion' => $request->partner_religion ? json_encode($request->partner_religion) : $profile->partner_religion,
            'partner_locations' => $request->partner_locations ? json_encode($request->partner_locations) : $profile->partner_locations,
            'main_photo' => $imageFilePath ?: $profile->main_photo,
            'additional_photos' => $additionalPhotoPaths ? json_encode($additionalPhotoPaths) : $profile->additional_photos,
            'is_complete' => $request->is_complete ?? $profile->is_complete,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'profile' => $profile,
            ],
        ]);
    }
    public function findYourLifePartner(Request $request)
    {
        $id = $request->user()->id;
        $gender = $request->input('gender');

        $age = $request->input('age');
        $ageMin = null;
        $ageMax = null;
        if (is_string($age)) {
            if (preg_match('/^(\\d+) to (\\d+)$/', $age, $matches)) {
                $ageMin = (int)$matches[1];
                $ageMax = (int)$matches[2];
            } elseif ($age === '40+') {
                $ageMin = 40;
                $ageMax = 100;
            }
        }

        $religion = $request->input('partner_religion');
        $location = $request->input('partner_locations');
        if (is_array($religion)) {
            $religion = implode(',', $religion);
        }
        if (is_array($location)) {
            $location = implode(',', $location);
        }

        $query = DB::table('profile_models')
            ->where('user_id', '!=', $id)
            ->where('is_verified', true)
            ->where('is_complete', true)
            ->when($gender, function ($q) use ($gender) {
                $q->where('gender', $gender);
            })
            ->where(function ($q) use ($religion, $location) {
                if ($religion) {
                    $q->where('partner_religion', 'like', '%' . trim((string) $religion) . '%');
                }
                if ($location) {
                    $parts = array_filter(array_map('trim', explode(',', (string) $location)));
                    foreach ($parts as $part) {
                        $q->orWhere('partner_locations', 'like', '%' . $part . '%');
                    }
                }
            });


        // Age: date_of_birth between now()->subYears($ageMax) and now()->subYears($ageMin)
        if ($ageMin !== null && $ageMax !== null) {
            $query->whereDate('date_of_birth', '<=', now()->subYears($ageMin))
                ->whereDate('date_of_birth', '>=', now()->subYears($ageMax));
        } elseif ($ageMin !== null) {
            $query->whereDate('date_of_birth', '<=', now()->subYears($ageMin));
        } elseif ($ageMax !== null) {
            $query->whereDate('date_of_birth', '>=', now()->subYears($ageMax));
        }

        Log::info('Finding matches with criteria', [
            'gender' => $gender,
            'age' => $age,
            'religion' => $religion,
            'location' => $location,
        ]);

        $tokens = [];
        if ($religion) {
            $tokens[] = ['col' => 'partner_religion', 'val' => trim((string) $religion)];
        }
        if ($location) {
            $location = trim((string) $location);
            $parts = array_filter(array_map('trim', explode(',', $location)));
            if (count($parts) > 0) {
                foreach ($parts as $part) {
                    $tokens[] = ['col' => 'partner_locations', 'val' => $part];
                }
            } else {
                $tokens[] = ['col' => 'partner_locations', 'val' => $location];
            }
        }

        if (!empty($tokens)) {
            $query->where(function ($q) use ($tokens) {
                foreach ($tokens as $i => $t) {
                    $col = $t['col'];
                    $val = $t['val'];
                    if ($i === 0) {
                        $q->where($col, 'like', '%' . $val . '%');
                    } else {
                        $q->orWhere($col, 'like', '%' . $val . '%');
                    }
                }
            });
        }

        // Log SQL for debugging
        try {
            Log::debug('SQL: ' . $query->toSql(), ['bindings' => $query->getBindings()]);
        } catch (\Exception $e) {
            Log::debug('Could not get SQL from query builder', ['error' => $e->getMessage()]);
        }

        $match = $query->orderBy('created_at', 'desc')->get();
        LOG::info('Matches found', ['count' => $match->count()]);
        return response()->json([
            'success' => true,
            'message' => 'Matches found successfully',
            'data' => [
                'matches' => $match,
            ],
        ]);
    }
    public function getAllProfiles(Request $request)
    {
        $id = $request->user()->id;
        $profiles = DB::table('profile_models')->where('user_id', '!=', $id)->where('is_verified', true)->where('is_complete', true)->orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'message' => 'Profiles retrieved successfully',
            'data' => [
                'profiles' => $profiles,
            ],
        ]);
    }
    
}
