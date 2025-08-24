<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class VideoController extends Controller
{
    public function createShorts(Request $request)
    {
        $request->validate([
            'videoUrl' => 'required|string',
            'caption' => 'nullable|string',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg'
        ]);

        $videoUrl = $request->videoUrl;
        $caption = $request->caption ?? '';
        $logoPath = $request->hasFile('logo') ? $request->file('logo')->getPathName() : null;

        $storageDir = storage_path('app/public/shorts');
        if (!file_exists($storageDir)) mkdir($storageDir, 0777, true);

        $fullVideoPath = $storageDir . '/video.mp4';

        // ⚠️ مسار Python الفعلي على جهازك
        $pythonPath = "C:\\Users\\Tags6\\AppData\\Local\\Programs\\Python\\Python312\\python.exe";
        $downloadProcess = new Process([
            $pythonPath,
            "-m",
            "yt_dlp",
            "-f",
            "bestvideo[ext=mp4]+bestaudio[ext=m4a]/mp4",
            "-o",
            $fullVideoPath,
            $videoUrl
        ]);
        $downloadProcess->run();

        if (!$downloadProcess->isSuccessful()) {
            return response()->json([
                'error' => $downloadProcess->getErrorOutput()
            ], 500);
        }

        // 2️⃣ الحصول على طول الفيديو
        $ffprobe = new Process(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "default=noprint_wrappers=1:nokey=1", $fullVideoPath]);
        $ffprobe->run();
        $duration = floatval(trim($ffprobe->getOutput()));

        $shorts = [];
        $maxLength = 60; // أقصى طول للشورت بالثواني
        $start = 0;
        $index = 1;

        // 3️⃣ تقسيم الفيديو لشورتات ≤ دقيقة
        while ($start < $duration) {
            $end = min($start + $maxLength, $duration);
            $shortPath = $storageDir . "/short_{$index}.mp4";

            // إعداد فلتر FFmpeg للشورت (كابشن + شعار)
            $filter = "";
            if ($caption) {
                $filter .= "drawtext=text='{$caption}':fontcolor=white:fontsize=36:box=1:boxcolor=black@0.5:boxborderw=10:x=(w-text_w)/2:y=h-80";
            }
            if ($logoPath) {
                $filter = "[0:v][1:v]overlay=W-w-10:10," . $filter;
            }

            $ffmpegCmd = [
                "ffmpeg",
                "-i",
                $fullVideoPath,
            ];

            if ($logoPath) $ffmpegCmd[] = "-i" . $logoPath;

            $ffmpegCmd = array_merge($ffmpegCmd, [
                "-ss",
                strval($start),
                "-to",
                strval($end),
                "-filter_complex",
                $filter ?: "null",
                "-c:v",
                "libx264",
                "-c:a",
                "aac",
                "-shortest",
                $shortPath
            ]);

            $ffmpegProcess = new Process($ffmpegCmd);
            $ffmpegProcess->run();

            if (!$ffmpegProcess->isSuccessful()) {
                return response()->json(['error' => $ffmpegProcess->getErrorOutput()], 500);
            }

            $shorts[] = asset("storage/shorts/short_{$index}.mp4");
            $start = $end;
            $index++;
        }

        return response()->json([
            'message' => 'Video split into shorts successfully!',
            'shorts' => $shorts
        ]);
    }
}
