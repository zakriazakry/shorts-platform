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
        $validator = Validator::make($request->all(), [
            'videoUrl' => 'required|string',
            'caption' => 'nullable|string',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $videoPath = storage_path('app/public/video.mp4');
        $logoPath = $request->hasFile('logo') ? $request->file('logo')->getPathName() : null;

        // تحميل الفيديو
        $process = new Process([base_path('tools/yt-dlp'), "-o", $videoPath, $request->videoUrl]);
        $process->run();
        if (!$process->isSuccessful()) return response()->json(['error' => $process->getErrorOutput()], 500);

        // معرفة مدة الفيديو بالثواني
        $ffprobe = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$videoPath}";
        $durationProcess = Process::fromShellCommandline($ffprobe);
        $durationProcess->run();
        $totalDuration = (int) trim($durationProcess->getOutput());

        $shortUrls = [];
        $start = 0;
        $index = 1;

        while ($start < $totalDuration) {
            $duration = min(60, $totalDuration - $start); // كل شورت ≤ 60 ثانية
            $outputPath = storage_path("app/public/final_{$index}.mp4");

            // تحضير الفلتر (شعار + كابشن)
            $filter = "drawtext=text='{$request->caption}':fontcolor=white:fontsize=36:box=1:boxcolor=black@0.5:boxborderw=10:x=(w-text_w)/2:y=h-80";
            if ($logoPath) $filter = "[0:v][1:v]overlay=W-w-10:10," . $filter;

            $command = "ffmpeg -i {$videoPath}" . ($logoPath ? " -i {$logoPath}" : "") .
                " -ss {$start} -t {$duration} -filter_complex \"{$filter}\" -c:v libx264 -c:a copy -shortest {$outputPath}";

            $process = Process::fromShellCommandline($command);
            $process->run();
            if (!$process->isSuccessful()) return response()->json(['error' => 'Failed to process video at part ' . $index], 500);

            $shortUrls[] = asset("storage/final_{$index}.mp4");
            $start += $duration;
            $index++;
        }

        return response()->json([
            'shorts' => $shortUrls
        ]);
    }
}
