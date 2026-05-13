<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;
use App\Models\AdminUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use App\Http\Requests\ProfileUpdateRequest;
use Google\Cloud\Firestore\FirestoreClient;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }
    public function chat(Request $request): View
{
    // Firestore initialize karo
    $firestore = new FirestoreClient([
        'projectId'   => 'home-fixing-8c250', // 🔴 Hardcoded Project ID
        'keyFilePath' => base_path('storage/app/firebase/service-account.json'), // 🔴 JSON ka path
        'transport'   => 'rest', // gRPC ki zarurat nahi
    ]);

    // "chats" collection fetch karo
    $chatsRef = $firestore->collection('chats');
    $documents = $chatsRef->documents();

    $chats = [];
    foreach ($documents as $document) {
        if ($document->exists()) {
            $chats[] = $document->data();
        }
    }

    // Debug: sab chat print karo
    // dd($chats);

    // View me chats bhejo
    return view('profile.chat', compact('chats'));
}

    /**
     * Update the user's profile information.
     */
public function update(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(AdminUsers::class)->ignore(request()->user()->id),
            ],
            'phone' => ['required', 'regex:/^\+9665[0-9]{8}$/', 'unique:users,phone,' . $request->user()->id],
            'password' => ['nullable', 'min:8'],
            'address' => ['required', 'string'],
        ]);
       $user = $request->user();

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
            'address' => $request->address,
        ]);

        $user->save();



        return Redirect::route('profile.edit')->with('success', 'profile updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
