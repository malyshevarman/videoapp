<?php
namespace App\Http\Controllers;

namespace App\Http\Controllers;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceOrder::select(
            'id',
            'public_url',
            'processStatus',
            'order_id',
            'created_at',
            'client'
        );

        // Проверяем, есть ли поиск
        if ($search = $request->input('table_search')) {
            $query->where('order_id', 'like', "%{$search}%")
                ->orWhereJsonContains('client->firstName', $search)
                ->orWhereJsonContains('client->lastName', $search);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends(['table_search' => $search]); // сохраняем параметр поиска при пагинации

        return view('admin.services.index', compact('orders'));
    }

    public function edit($id)
    {
        $service = ServiceOrder::findOrFail($id);
        return view('admin.services.edit', compact('service'));
    }

    public function video($id)
    {
        $service = ServiceOrder::findOrFail($id);
        return view('admin.services.video', compact('service'));
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $service->update($request->all());

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully');
    }

    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully');
    }
}
