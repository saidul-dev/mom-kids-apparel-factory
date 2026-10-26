<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

/**
 * Demo copy for the storefront's About / Mission-Vision-Values section (see
 * website/about.blade.php and website/partials/mission-vision-values.blade.php)
 * — otherwise those sections stay empty on a fresh install.
 */
class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        $company = CompanySetting::current();

        // Never overwrite text a client already wrote in Admin > Settings > Website.
        if ($company->about_text || $company->mission_text || $company->vision_text || $company->values_text) {
            return;
        }

        $company->update([
            'about_text' => 'Mom & Kids Apparel makes comfortable, good-quality clothing for babies, kids and '
                .'mothers. Every piece is cut, sewn and finished in our own factory in Bangladesh, and sold '
                .'directly from the factory and through our wholesale point.',
            'mission_text' => 'To dress every child and mother in clothes that are soft, safe and well made — '
                .'at a fair price, delivered on time to every retailer and customer we serve.',
            'vision_text' => 'To be the most trusted mom & kids garments maker in Bangladesh, known for quality '
                .'fabric, careful finishing and honest wholesale prices.',
            'values_text' => 'Quality Fabric, Child Safety, Fair Price, On-time Delivery',
        ]);
    }
}
