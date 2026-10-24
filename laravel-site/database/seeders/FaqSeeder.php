<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeData::all()['faqs'] as $index => $faq) {
            Faq::updateOrCreate(['question' => $faq['q']], [
                'answer' => $faq['a'],
                'sort_order' => $index,
            ]);
        }
    }
}
