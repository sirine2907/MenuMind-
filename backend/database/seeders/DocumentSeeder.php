<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    public function run(DocumentService $documents): void
    {
        $restaurant = Restaurant::current();
        $admin = User::where('email', 'admin@menumind.tn')->first();

        foreach (['allergen-policy.md', 'opening-hours-delivery-faq.md'] as $file) {
            $text = (string) file_get_contents(__DIR__.'/documents/'.$file);
            preg_match('/^#\s+(.+)$/m', $text, $m);

            $documents->ingestText($restaurant, trim($m[1] ?? $file), $text, $file, 'text/markdown', $admin);
        }
    }
}
