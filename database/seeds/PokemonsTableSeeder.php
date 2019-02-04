<?php
use Illuminate\Database\Seeder;
class PokemonsTableSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // check if table users is empty
        if(DB::table('users')->get()->count() == 0){
            DB::table('users')->insert([
                [
                    'name' => 'florian',
                    'email' => 'florian@voyelle.fr',
                    'password' => bcrypt('florian'),
                    'discord_id' => 539079553813839873,
                    'discord_name' => 'Ninadjeret',
                    'guilds'    => '["377440443258109953", "400277491941638147"]',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                [
                    'name' => 'kisulov',
                    'email' => 'kisulov@kisulov.fr',
                    'password' => bcrypt('kisulov'),
                    'discord_id' => 484022213507547152,
                    'discord_name' => 'Kisulov',
                    'guilds'    => '["377440443258109953"]',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
            ]);
        } // end check if table users is empty

        // check if table users is empty
        if(DB::table('cities')->get()->count() == 0){
            DB::table('cities')->insert([
                [
                    'name' => 'Rennes',
                    'slug' => 'rennes',
                ],
                [
                    'name' => 'Dijon',
                    'slug' => 'dijon',
                ],
                [
                    'name' => 'Besançon',
                    'slug' => 'besancon',
                ],
                [
                    'name' => 'Thionville',
                    'slug' => 'thionville',
                ]
            ]);
        } // end check if table users is empty

        if(DB::table('guilds')->get()->count() == 0){
            DB::table('guilds')->insert([
                [
                    'discord_id' => '377440443258109953',
                    'name'  => 'Pokemon Go Rennes',
                    'type'  => 'discord',
                    'city_id'  => 1,
                    'access_rule' => 'specific_roles',
                    'authorized_roles' => json_encode(['377449927321845771', '494618121366143027'])
                ],
                [
                    'discord_id' => '400277491941638147',
                    'name'  => 'Pokemon GO - Dijon',
                    'type'  => 'discord',
                    'city_id'  => 2,
                    'access_rule' => 'everyone',
                    'authorized_roles' => json_encode(['377449927321845771', '494618121366143027'])
                ],
            ]);
        } // end check if table users is empty

        if(DB::table('zones')->get()->count() == 0){
            DB::table('zones')->insert([
                [
                    'name'  => 'Vern Sur Seiche',
                    'city_id'  => 1,
                ],
            ]);
        } // end check if table users is empty

        if(DB::table('stops')->get()->count() == 0){
            DB::table('stops')->insert([
                [
                    'niantic_name' => 'Vern sur Seiche - Église',
                    'name'  => 'Église',
                    'gym'  => true,
                    'city_id'  => 1,
                    'zone_id'  => 1,
                ],
                [
                    'niantic_name' => 'Le Clos D\'orriere',
                    'name'  => 'Clos d\'Orrière',
                    'gym'  => true,
                    'city_id'  => 1,
                    'zone_id'  => 1,
                ],
            ]);
        } // end check if table users is empty

        $game_master = file_get_contents('https://raw.githubusercontent.com/pokemongo-dev-contrib/pokemongo-game-master/master/versions/latest/GAME_MASTER.json');
        $game_master = json_decode($game_master);
        if(DB::table('pokemons')->get()->count() == 0){
            foreach( $game_master as $game_master_2 ) {
                if( is_array($game_master_2) ) { foreach( $game_master_2 as $node ) {
                if( !isset($node->pokemonSettings) || empty($node->pokemonSettings) ) continue;
                if( strstr($node->templateId, 'ALOLA')) continue;
                if( strstr($node->templateId, 'NORMAL')) continue;
                error_log('Import de '.$node->pokemonSettings->pokemonId);
                DB::table('pokemons')->insert([
                    [
                        'pokedex_id' => substr($node->templateId, 2, 3),
                        'niantic_id'  => $node->pokemonSettings->pokemonId,
                        'base_att'  => $node->pokemonSettings->stats->baseAttack,
                        'base_def'  => $node->pokemonSettings->stats->baseDefense,
                        'base_sta'  => $node->pokemonSettings->stats->baseStamina,
                    ],
                ]);
            }}}
        } // end check if table users is empty


    }
}
