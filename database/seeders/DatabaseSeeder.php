<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            SettingSeeder::class,
            SiteContentSeeder::class,
            FooterNavigationSeeder::class,
        ]);

        User::query()->firstOrCreate(
            ['email' => 'admin@rumaorg.com'],
            ['name' => 'Site Admin', 'password' => '
About Us
About RUMA Organic

🌾Our Mission
To empower Myanmar agriculture through high-quality seeds, Humic-based organic fertilizers, and soil-restoration solutions developed through Russian–Myanmar cooperation and agricultural expertise.
We are committed to:
Improving soil health by restoring essential nutrients and supporting a healthy soil environment.
Providing farmers with high-quality seeds and effective agricultural solutions to improve crop quality, productivity, and profitability.
Promoting responsible and sustainable agricultural practices that protect soil and natural resources.
Supporting farmers in achieving better yields, better income, and a better quality of life.
Contributing to a healthier food system for Myanmar communities.
Bringing together Russian agricultural technology and Myanmar agricultural knowledge to create practical solutions for local farmers.
Building long-term partnerships with farmers, agricultural professionals, businesses, and communities to create shared value across the agricultural sector.


🌱 Our Vision:
To build a healthier and more sustainable agriculture where farmers prosper, people have access to healthier food, and fertile soil is preserved for generations to come.
တောင်သူများ ဝင်ငွေတိုးတက်ပြီး လူနေမှုဘဝ ပိုမိုကောင်းမွန်လာစေရန်၊ ပြည်သူများအတွက် ပိုမိုကျန်းမာရေးနှင့်ညီညွတ်သော အစားအစာများ ရရှိနိုင်စေရန်နှင့် နောင်လာနောက်သားများအတွက် မြေဆီသြဇာပြည့်ဝပြီး ကျန်းမာသော ရေမြေများကို ထိန်းသိမ်းလက်ဆင့်ကမ်းနိုင်စေရန် ရေရှည် တည်တံ့သော စိုက်ပျိုးရေးကို တည်ဆောက်ရန်။

💚 Core Values:
1. Farmer First
We put farmers at the heart of everything we do, focusing on practical solutions that improve productivity, profitability, and quality of life.
တောင်သူကိုအဓိကထားခြင်း တောင်သူတွေရဲ့ လက်တွေ့ကျတဲ့လိုအပ်ချက်၊ အထွက်နှုန်း၊ ဝင်ငွေနဲ့ လူနေမှုဘဝတိုးတက်ရေးကို အဓိကထားဆောင်ရွက်ခြင်း။

2. Soil Health
We believe healthy soil is the foundation of healthy crops, healthy food, and sustainable agriculture.
မြေဆီလွှာကျန်းမာရေး ကျန်းမာသောသီးနှံ၊ ပိုမိုကောင်းမွန်သောအစားအစာနဲ့ ရေရှည်တည်တံ့တဲ့ စိုက်ပျိုးရေးအတွက် ကျန်းမာတဲ့မြေဆီလွှာက အခြေခံအုတ်မြစ်ဖြစ်တယ်လို့ ယုံကြည်ခြင်း။

3. Quality & Integrity
We are committed to delivering reliable products, maintaining high standards, and doing business with honesty and responsibility.
အရည်အသွေးနှင့်ဂုဏ်သိက္ခာ အရည်အသွေးကောင်းမွန်သော ထုတ်ကုန်များ ပေးအပ်ခြင်း၊ စံချိန်စံညွှန်းများကို ထိန်းသိမ်းခြင်းနှင့် ရိုးသားတာဝန်ယူမှုရှိစွာ လုပ်ကိုင်ခြင်း။

4. Innovation & Knowledge
We connect agricultural knowledge, technology, and practical experience to develop better solutions for Myanmar agriculture.
ဆန်းသစ်တီထွင်မှုနှင့်အသိပညာ နည်းပညာ၊ စိုက်ပျိုးရေးအသိပညာနဲ့ လက်တွေ့အတွေ့အကြုံတွေကို ပေါင်းစပ်ပြီး မြန်မာ့စိုက်ပျိုးရေး အတွက် ပိုမိုကောင်းမွန်တဲ့ ဖြေရှင်းချက်တွေ ဖော်ဆောင်ခြင်း။

5. Sustainability
We work to protect soil, natural resources, and agricultural productivity for the benefit of present and future generations.
ရေရှည်တည်တံ့မှု လက်ရှိမျိုးဆက်သာမက နောင်လာနောက်သားများအတွက်ပါ မြေဆီလွှာ၊ သဘာဝအရင်းအမြစ်များနှင့် စိုက်ပျိုးထုတ်လုပ်နိုင်စွမ်းကို ထိန်းသိမ်းခြင်း။

6. Partnership & Shared Prosperity
We believe agriculture grows stronger when farmers, businesses, professionals, and communities grow together.

ပူးပေါင်းဆောင်ရွက်မှုနှင့်သာယာဝ‌ပြောတိုးတက်မှု တောင်သူ၊ စီးပွားရေးလုပ်ငန်းများ၊ စိုက်ပျိုးရေးပညာရှင်များနှင့် လူမှုအသိုင်းအဝိုင်းတို့ အတူတကွ တိုးတက်လာမှ စိုက်ပျိုးရေးကဏ္ဍလည်း ပိုမိုခိုင်မာလာမည်ဟု ယုံကြည်ခြင်း။




Our Teams:


Oleg
Director of RUMA Organic
U Thurein Oo
General Manager
Daw Khin Thida Nyein
Senior Product Manager

Our People:
Our Partners: NAS, MSG', 'is_admin' => true],
        );
    }
}
