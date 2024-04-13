<?php

namespace Database\Seeders;

use App\Models\PostCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Semua',
                'description' => 'General category',
            ],
            [
                'name' => 'Teknologi',
                'description' => 'Technology category',
            ],
            [
                'name' => 'Gaya Hidup',
                'description' => 'Lifestyle category',
            ],
            [
                'name' => 'Dapur',
                'description' => 'Kitchen category',
            ],
            [
                'name' => 'Olahraga',
                'description' => 'Sports category',
            ],
            [
                'name' => 'Kesehatan',
                'description' => 'Health category',
            ],
            [
                'name' => 'Pendidikan',
                'description' => 'Education category',
            ],
            [
                'name' => 'Bisnis',
                'description' => 'Business category',
            ],
            [
                'name' => 'Digital',
                'description' => 'Digital category',
            ],
            [
                'name' => 'Otomotif',
                'description' => 'Automotive category',
            ],
            [
                'name' => 'Properti',
                'description' => 'Property category',
            ],
            [
                'name' => 'Handphone',
                'description' => '',
                'parent_id' => 2,  
            ],
            [
                'name' => 'Laptop',
                'description' => '',
                'parent_id' => 2,  
            ],
            [
                'name' => 'Komputer',
                'description' => '',
                'parent_id' => 2,
            ],
            [
                'name' => 'Tablet',
                'description' => '',
                'parent_id' => 2,  
            ],
            [
                'name' => 'Fashion Wanita',
                'description' => '',
                'parent_id' => 3,
            ],
            [
                'name' => 'Fashion Pria',
                'description' => '',
                'parent_id' => 3,
            ],
            [
                'name' => 'Fashion Anak',
                'description' => '',
                'parent_id' => 3,
            ],
            [
                'name' => 'Kecantikan',
                'description' => '',
                'parent_id' => 3,
            ],
            [
                'name' => 'Jam Tangan',
                'description' => '',
                'parent_id' => 3,
            ],
            [
                'name' => 'Sepatu',
                'description' => '',
                'parent_id' => 3,
            ],
            [
                'name' => 'Motor',
                'description' => '',
                'parent_id' => 10,
            ],
            [
                'name' => 'Mobil',
                'description' => '',
                'parent_id' => 10,
            ],
            [
                'name' => 'Sparepart',
                'description' => '',
                'parent_id' => 10,
            ],
            [
                'name' => 'Aksesoris',
                'description' => '',
                'parent_id' => 10,
            ],
            [
                'name' => 'Rumah',
                'description' => '',
                'parent_id' => 11,
            ],
            [
                'name' => 'Tanah',
                'description' => '',
                'parent_id' => 11,
            ],
            [
                'name' => 'Apartemen',
                'description' => '',
                'parent_id' => 11,
            ],
            [
                'name' => 'Disewakan',
                'description' => '',
                'parent_id' => 11,
            ]
        ];
        PostCategory::truncate();
        
        foreach ($categories as $category) {
            PostCategory::create($category);
        }
    }
}
