<?php

declare(strict_types=1);

namespace App\Controllers\Provider;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class TrailerListingController extends Controller
{
    private const TYPES = ['box','car','boat','camper','caravan','horse_float','plant','tipper','commercial','other'];
    private const AVAILABILITY = ['new','used','hire'];

    public function index(Request $request): Response
    {
        $provider = $this->provider();
        return $this->view('provider.trailer-listings', ['title'=>'Trailer listings','provider'=>$provider,'listings'=>Database::select('SELECT * FROM trailer_listings WHERE provider_id = ? AND brand_id = 3 AND deleted_at IS NULL ORDER BY created_at DESC', [(int)$provider['id']])]);
    }

    public function form(Request $request): Response
    {
        $provider = $this->provider();
        $id = (int)$request->query('id', 0);
        $listing = $id > 0 ? Database::selectOne('SELECT * FROM trailer_listings WHERE id = ? AND provider_id = ? AND brand_id = 3 AND deleted_at IS NULL', [$id,(int)$provider['id']]) : null;
        if ($id > 0 && $listing === null) { $this->abort(404); }
        return $this->view('provider.trailer-listing-form', ['title'=>$listing?'Edit trailer':'Add trailer','listing'=>$listing,'types'=>self::TYPES]);
    }

    public function save(Request $request): Response
    {
        $provider = $this->provider();
        $id = (int)$request->input('id', 0);
        if ($id > 0 && Database::selectOne('SELECT id FROM trailer_listings WHERE id = ? AND provider_id = ? AND brand_id = 3 AND deleted_at IS NULL', [$id,(int)$provider['id']]) === null) { $this->abort(404); }
        $title = trim((string)$request->input('title'));
        $description = trim((string)$request->input('description'));
        $type = (string)$request->input('trailer_type');
        $availability = (string)$request->input('listing_type');
        if ($title === '' || $description === '' || !in_array($type,self::TYPES,true) || !in_array($availability,self::AVAILABILITY,true)) {
            return $this->redirectWith('/provider/trailer-listings/form'.($id?'?id='.$id:''),'error','Title, description, trailer type and availability are required.');
        }
        $values = [$title,$availability,$type,$description,trim((string)$request->input('make'))?:null,trim((string)$request->input('model'))?:null,(int)$request->input('model_year')?:null,is_numeric($request->input('price_aud'))?(float)$request->input('price_aud'):null,is_numeric($request->input('atm_kg'))?(float)$request->input('atm_kg'):null,is_numeric($request->input('tare_kg'))?(float)$request->input('tare_kg'):null,trim((string)$request->input('location_text'))?:null];
        if ($id > 0) {
            Database::affecting('UPDATE trailer_listings SET title=?, listing_type=?, trailer_type=?, description=?, make=?, model=?, model_year=?, price_aud=?, atm_kg=?, tare_kg=?, location_text=?, status=\'pending\', updated_at=NOW() WHERE id=? AND provider_id=?', [...$values,$id,(int)$provider['id']]);
        } else {
            $slug = $this->slug($title);
            Database::insert('INSERT INTO trailer_listings (brand_id,provider_id,slug,title,listing_type,trailer_type,description,make,model,model_year,price_aud,atm_kg,tare_kg,location_text,status,created_at) VALUES (3,?,?,?,?,?,?,?,?,?,?,?,?,?,\'pending\',NOW())', [(int)$provider['id'],$slug,...$values]);
        }
        return $this->redirectWith('/provider/trailer-listings','success','Trailer listing saved and submitted for review.');
    }

    private function provider(): array
    {
        if (current_brand()->id() !== 'trailerwise') { $this->abort(404); }
        $provider = Database::selectOne('SELECT * FROM providers WHERE user_id = ? AND deleted_at IS NULL', [(int)(current_user()['id']??0)]);
        if ($provider === null) { $this->abort(403,'No provider profile is linked to this account.'); }
        return $provider;
    }

    private function slug(string $title): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/','-',strtolower($title))??'','-') ?: 'trailer';
        $slug=$base; $i=2;
        while (Database::selectOne('SELECT id FROM trailer_listings WHERE brand_id=3 AND slug=?',[$slug])!==null) { $slug=$base.'-'.$i++; }
        return $slug;
    }
}
