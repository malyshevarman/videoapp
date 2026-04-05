<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class ThemeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $likeOperator = Theme::query()->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';

        $themes = Theme::query()
            ->withCount('dealers')
            ->when($search !== '', function ($query) use ($search, $likeOperator) {
                $searchId = is_numeric($search) ? (int) $search : null;

                $query->where(function ($subQuery) use ($search, $searchId, $likeOperator) {
                    $subQuery->where('name', $likeOperator, '%' . $search . '%');

                    if ($searchId !== null) {
                        $subQuery->orWhere('id', $searchId);
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.themes.index', compact('themes', 'search'));
    }

    public function create()
    {
        return view('admin.themes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $theme = Theme::create([
            'name' => $validated['name'],
        ]);

        $this->prepareLogoImage($request->file('logo'));
        $theme->addMediaFromRequest('logo')->toMediaCollection('logo');

        return redirect()->route('admin.themes.index')
            ->with('success', 'Тема создана.');
    }

    public function edit(Theme $theme)
    {
        return view('admin.themes.edit', compact('theme'));
    }

    public function update(Request $request, Theme $theme)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $theme->update([
            'name' => $validated['name'],
        ]);

        if ($request->hasFile('logo')) {
            $this->prepareLogoImage($request->file('logo'));
            $theme->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return redirect()->route('admin.themes.index')
            ->with('success', 'Тема обновлена.');
    }

    public function destroy(Theme $theme)
    {
        if ($theme->dealers()->exists()) {
            return redirect()->route('admin.themes.index')
                ->with('error', 'Нельзя удалить тему, пока она привязана к дилерам.');
        }

        $theme->delete();

        return redirect()->route('admin.themes.index')
            ->with('success', 'Тема удалена.');
    }

    protected function prepareLogoImage(?UploadedFile $file): void
    {
        if (!$file) {
            return;
        }

        $imageSize = @getimagesize($file->getPathname());

        if (!$imageSize) {
            return;
        }

        [$width, $height] = $imageSize;

        if ($width <= 500 && $height <= 500) {
            return;
        }

        Image::load($file->getPathname())
            ->fit(Fit::Max, 500, 500)
            ->save();
    }
}
