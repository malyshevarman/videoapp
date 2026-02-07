<?php
namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VideoController extends Controller
{
    public function upload(Request $request, $serviceOrderId)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,avi,mov,wmv|max:512000', // 500MB max
            'timecodes' => 'present|array',
        ]);

        $serviceOrder = ServiceOrder::findOrFail($serviceOrderId);
        $file = $request->file('video');

        // Генерируем уникальное имя файла
        $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = 'videos/' . $filename;

        // 1. Сохраняем оригинал
        $originalPath = $file->storeAs('videos/original', $filename);
        $fullOriginalPath = storage_path('app/' . $originalPath);
        $fullFinalPath = storage_path('app/' . $path);

        // 2. FFmpeg faststart
        $process = new Process([
            'ffmpeg',
            '-y',
            '-i', $fullOriginalPath,
            '-movflags', 'faststart',
            '-c', 'copy',
            $fullFinalPath
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('FFmpeg error: ' . $process->getErrorOutput());
        }

        // 3. Удаляем оригинал
        Storage::delete($originalPath);

        // 4. Сохраняем в БД
        $video = $serviceOrder->videos()->create([
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => filesize($fullFinalPath),
            'mime_type' => 'video/mp4',
            'timecodes' => json_decode($request->input('timecodes'), true),
        ]);

        return response()->json([
            'success' => true,
            'video' => $video,
            'url' => Storage::url($path),
        ]);
    }

    public function destroy($id)
    {
        $video = Video::findOrFail($id);
        $video->delete(); // Файл удалится автоматически через событие в модели

        return response()->json(['success' => true]);
    }
}
