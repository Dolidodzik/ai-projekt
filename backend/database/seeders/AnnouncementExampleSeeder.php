<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Image;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class AnnouncementExampleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('is_admin', true)->first();

        if (! $admin) {
            throw new RuntimeException('Missing admin user.');
        }

        $uploadsPath = public_path('uploads/announcements');
        if (! is_dir($uploadsPath)) {
            mkdir($uploadsPath, 0775, true);
        }

        $examples = [
            [
                'title' => 'przykladowe ogloszenie 1',
                'url' => 'https://placehold.co/800x600/png',
            ],
            [
                'title' => 'przykladowe ogloszenie 2',
                'url' => 'https://placehold.co/400x400/png',
            ],
        ];

        foreach ($examples as $example) {
            $slug = Str::slug($example['title']);
            $fileName = $slug . '.png';
            $relativePath = 'uploads/announcements/' . $fileName;
            $body = '<p>' . e($example['title']) . '</p>';
            $content = $body . '<p><img src="/' . $relativePath . '" alt=""></p>';

            $fileBytes = file_get_contents($example['url']);
            if ($fileBytes === false) {
                throw new RuntimeException('Failed to download image: ' . $example['url']);
            }

            file_put_contents($uploadsPath . '/' . $fileName, $fileBytes);

            Image::updateOrCreate(
                ['file_name' => $relativePath],
                ['uuid' => $slug]
            );

            Announcement::updateOrCreate(
                [
                    'admin_id' => $admin->id,
                    'title' => $example['title'],
                ],
                [
                    'content' => $content,
                ]
            );
        }
    }
}