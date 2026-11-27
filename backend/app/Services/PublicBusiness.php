<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class PublicBusiness
{
    public static function current(): ?Shop
    {
        $id = DB::table('platform_settings')->where('id', 1)->value('public_shop_id');

        return ($id ? Shop::find($id) : null)
            ?? Shop::orderByRaw("case when status = 'active' then 0 else 1 end")->orderBy('id')->first();
    }
}
