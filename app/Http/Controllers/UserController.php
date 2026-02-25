<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));

        $users = User::query()
            ->with('dealers:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('id', $search);
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create()
    {
        $dealers = Dealer::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.users.create', compact('dealers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'manager'])],
            'dealer_ids' => ['nullable', 'array'],
            'dealer_ids.*' => ['integer', 'exists:dealers,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_admin' => $validated['role'] === 'admin',
        ]);

        $user->dealers()->sync($validated['dealer_ids'] ?? []);

        return redirect()->route('admin.users.index')
            ->with('success', 'Пользователь создан.');
    }

    public function edit(User $user)
    {
        $dealers = Dealer::query()->orderBy('name')->get(['id', 'name']);
        $user->load('dealers:id');

        return view('admin.users.edit', compact('user', 'dealers'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'manager'])],
            'dealer_ids' => ['nullable', 'array'],
            'dealer_ids.*' => ['integer', 'exists:dealers,id'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_admin' => $validated['role'] === 'admin',
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);
        $user->dealers()->sync($validated['dealer_ids'] ?? []);

        return redirect()->route('admin.users.index')
            ->with('success', 'Пользователь обновлён.');
    }

    public function destroy(User $user)
    {
        $currentUser = auth()->user();

        if ($currentUser && $currentUser->id === $user->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Нельзя удалить текущего пользователя.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Пользователь удалён.');
    }
}
