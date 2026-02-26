<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class DealerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $likeOperator = Dealer::query()->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';

        $dealers = Dealer::query()
            ->when($search !== '', function ($query) use ($search, $likeOperator) {
                $searchId = is_numeric($search) ? (int) $search : null;

                $query->where(function ($subQuery) use ($search, $searchId, $likeOperator) {
                    $subQuery->where('name', $likeOperator, '%' . $search . '%')
                        ->orWhere('external_id', $likeOperator, '%' . $search . '%');

                    if ($searchId !== null) {
                        $subQuery->orWhere('id', $searchId);
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.dealers.index', compact('dealers', 'search'));
    }

    public function create()
    {
        return view('admin.dealers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'external_id' => ['nullable', 'string', 'max:255', 'unique:dealers,external_id'],
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $dealer = Dealer::create([
            'external_id' => $validated['external_id'] ?? null,
            'name' => $validated['name'],
        ]);

        if ($request->hasFile('logo')) {
            $this->prepareLogoImage($request->file('logo'));
            $dealer->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return redirect()->route('admin.dealers.index')
            ->with('success', 'Дилер создан.');
    }

    public function edit(Dealer $dealer)
    {
        return view('admin.dealers.edit', compact('dealer'));
    }

    public function update(Request $request, Dealer $dealer)
    {
        $validated = $request->validate([
            'external_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('dealers', 'external_id')->ignore($dealer->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $dealer->update([
            'external_id' => $validated['external_id'] ?? null,
            'name' => $validated['name'],
        ]);

        if ($request->boolean('remove_logo')) {
            $dealer->clearMediaCollection('logo');
        }

        if ($request->hasFile('logo')) {
            $this->prepareLogoImage($request->file('logo'));
            $dealer->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return redirect()->route('admin.dealers.index')
            ->with('success', 'Данные дилера обновлены.');
    }

    public function destroy(Dealer $dealer)
    {
        $dealer->delete();

        return redirect()->route('admin.dealers.index')
            ->with('success', 'Дилер удалён.');
    }

    protected function prepareLogoImage(UploadedFile $file): void
    {
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
