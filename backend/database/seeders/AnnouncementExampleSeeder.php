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
                'width' => 800,
                'height' => 600,
            ],
            [
                'title' => 'przykladowe ogloszenie 2',
                'width' => 400,
                'height' => 400,
            ],
        ];

        foreach ($examples as $example) {
            $slug = Str::slug($example['title']);
            $fileName = $slug.'.png';
            $relativePath = 'uploads/announcements/'.$fileName;
            $absolutePath = $uploadsPath.'/'.$fileName;
            $body = '<p>'.e($example['title']).'</p>';
            $content = $body.'<p><img src="/'.$relativePath.'" alt=""></p>';

            $this->createPlaceholderImage(
                $absolutePath,
                $example['width'],
                $example['height'],
                $example['title'],
            );

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

    private function createPlaceholderImage(string $path, int $width, int $height, string $label): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD extension is required to seed announcement images.');
        }

        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 23, 84, 216);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        $textHeight = imagefontheight($font);
        $x = max(0, (int) (($width - $textWidth) / 2));
        $y = max(0, (int) (($height - $textHeight) / 2));
        imagestring($image, $font, $x, $y, $label, $textColor);

        if (! imagepng($image, $path)) {
            imagedestroy($image);
            throw new RuntimeException('Failed to write placeholder image: '.$path);
        }

        imagedestroy($image);
    }
}
