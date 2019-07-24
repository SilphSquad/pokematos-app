<?php

namespace App\Http\Controllers;

use App\User;
use App\models\City;
use App\models\Role;
use App\models\Raid;
use App\models\Guild;
use RestCord\DiscordClient;
use App\models\RoleCategory;
use Illuminate\Http\Request;
use App\ImageAnalyzer\Engine;
use Illuminate\Support\Facades\Hash;

class BotController extends Controller {

    /**
     * [getRoles description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getRoles( Request $request ) {
        $guild = Guild::where('discord_id', $request->guild_id)->first();
        if( empty($guild) ) return response()->json('L\'ID de la guild n\'existe pas', 400);

        $roles = Role::where('guild_id', $guild->id)->get();
        return response()->json($roles, 200);
    }


    /**
     * [getRole description]
     * @param  Request $request [description]
     * @param  [type]  $role    [description]
     * @return [type]           [description]
     */
    public function getRole( Request $request, $role ) {
        $role = Role::where('discord_id', $role)->first();
        if( empty($role) ) return response()->json('Le role n\'a pas été trouvé', 400);

        $return = $role->toArray();
        unset($return['guild']['settings']);
        if( $return['category'] ) {
            unset($return['category']['guild']);
        }

        return response()->json($return, 200);
    }


    /**
     * Création d'un role
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function createRole( Request $request ) {

        $guild = Guild::where('discord_id', $request->guild_id)->first();
        if( empty($guild) ) return response()->json('L\'ID de la guild n\'existe pas', 400);

        $roleCategory = RoleCategory::find($request->category_id);
        if( empty($roleCategory) ) return response()->json('La catégorie n\'existe pas', 400);

        $discord = new DiscordClient(['token' => config('discord.token')]);
        $discord_role = $discord->guild->createGuildRole([
            'guild.id' => (int) $request->guild_id,
            'name' => $request->name,
        ]);
        if( !$discord_role ) return response()->json('Le role n\'a pas pu être créé sur Discord', 400);

        $role = Role::create([
            'discord_id' => $discord_role->id,
            'guild_id' => $guild->id,
            'category_id' => $roleCategory->id,
            'name' => $request->name,
            'type' => ( $request->type ) ? $request->type : null,
            'relation_id' => ( $request->relation_id ) ? $request->relation_id : null,
        ]);

        $discord->gateway->getGateway();
        $discord->channel->createMessage([
            'channel.id' => (int) $roleCategory->channel_discord_id,
            'content' => '<@&'.$role->discord_id.'>'
        ]);

        return response()->json($role, 200);
    }


    /**
     * [deleteRole description]
     * @param  Request $request [description]
     * @param  [type]  $role    [description]
     * @return [type]           [description]
     */
    public function deleteRole( Request $request, $role ) {

        $role = Role::where('discord_id', $role)->first();
        if( empty($role) ) return response()->json('Le role n\'a pas été trouvé', 400);

        $discord = new DiscordClient(['token' => config('discord.token')]);
        $discord_role = $discord->guild->deleteGuildRole([
            'guild.id' => (int) $role->guild->discord_id,
            'role.id' => (int) $role->discord_id
        ]);

        Role::destroy($role->id);

        return response()->json(null, 204);
    }


    /**
     * [updateRole description]
     * @param  Request $request [description]
     * @param  [type]  $role    [description]
     * @return [type]           [description]
     */
    public function updateRole( Request $request, $role ) {

        $role = Role::where('discord_id', $role)->first();
        if( empty($role) ) return response()->json('Le role n\'a pas été trouvé', 400);

        $discord = new DiscordClient(['token' => config('discord.token')]);
        $discord_role = $discord->guild->modifyGuildRole([
            'guild.id' => (int) $role->guild->discord_id,
            'role.id' => (int) $role->discord_id,
            'name' => $request->name,
        ]);

        if( $request->name ) $role->update([ 'name' => $request->name ]);


        return response()->json($role, 200);
    }

    /**
    * ==================================================================
    * GESTION DES CATEGORIES DE ROLES
    * ==================================================================
    */

    /**
     * [getRoleCategories description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function getRoleCategories( Request $request) {

        $guild = Guild::where('discord_id', $request->guild_id)->first();
        if( empty($guild) ) return response()->json('L\'ID de la guild n\'existe pas', 400);

        $roleCategories = RoleCategory::where('guild_id', $guild->id);
        return response()->json($roleCategories, 200);
    }


    /**
     * [createRoleCategory description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function createRoleCategory( Request $request ) {

        $guild = Guild::where('discord_id', $request->guild_id)->first();
        if( empty($guild) ) return response()->json('L\'ID de la guild n\'existe pas', 400);

        $roleCategory = RoleCategory::create([
            'guild_id' => $guild->id,
            'name' => $request->name,
            'channel_discord_id' => $request->channel_id,
            'restricted' => ($request->restricted) ? $request->restricted : 0,
        ]);
        return response()->json($roleCategory, 200);
    }

    public function updateRoleCategory( Request $request, RoleCategory $categorie ) {

        $guild = Guild::where('discord_id', $request->guild_id)->first();
        if( empty($guild) ) return response()->json('L\'ID de la guild n\'existe pas', 400);

        $roleCategory ->update([
            'name' => ($request->name) ? $request->name : $roleCategory->name,
            'channel_discord_id' => ($request->channel_id) ? $request->channel_id : $roleCategory->channel_discord_id,
            'restricted' => ($request->restricted) ? $request->restricted : $roleCategory->restricted,
        ]);
        return response()->json($roleCategory, 200);
    }


    /**
     * [getRoleCategory description]
     * @param  Request      $request   [description]
     * @param  RoleCategory $categorie [description]
     * @return [type]                  [description]
     */
    public function getRoleCategory( Request $request, RoleCategory $categorie ) {
        return response()->json($categorie, 200);
    }


    /**
     * [deleteRoleCategory description]
     * @param  Request      $request   [description]
     * @param  RoleCategory $categorie [description]
     * @return [type]                  [description]
     */
    public function deleteRoleCategory( Request $request, RoleCategory $categorie ) {
        RoleCategoy::destroy($categorie->id);
        return response()->json(null, 204);
    }

    /**
     * [decodeImage description]
     * @param  Request $request [description]
     * @param  City    $city    [description]
     * @return [type]           [description]
     */
    public function addRaid( Request $request ) {

        $url = ( isset($request->url) && !empty($request->url) ) ? $request->url : false ;
        $text = ( isset($request->text) && !empty($request->text) ) ? $request->text : false ;
        $username = $request->user_name;
        $userDiscordId = $request->user_discord_id;
        $guild_discord_id = $request->guild_discord_id;

        if( empty( $guild_discord_id ) ) {
            return response()->json('L\'ID de Guild est obligatoire', 400);
        }

        $guild = Guild::where( 'discord_id', $guild_discord_id )->first();
        $city = City::find( $guild->city->id );

        $user = User::where('discord_id', $userDiscordId)->first();
        if( !$user ) {
            $user = User::create([
                'name' => $username,
                'password' => Hash::make( str_random(20) ),
                'discord_name' => $username,
                'discord_id' => $userDiscordId,
            ]);
        }

        if( $url ) {
            $imageAnalyzer = new ImageAnalyzer($url, $guild);
            $result = $imageAnalyzer->result;
        } else {

        }

        $args = [];
        $args['city_id'] = $city->id;
        $args['user_id'] = $user->id;
        $args['gym_id'] = $result->gym->id;
        $args['source_type'] = 'image';
        if( isset( $result->pokemon->id ) ) $args['pokemon_id'] = $result->pokemon->id;
        if( isset( $result->eggLevel ) ) $args['egg_level'] = $result->eggLevel;
        if( isset( $result->date ) ) $args['start_time'] = $result->date;

        $raid = Raid::add($args);
        return response()->json($raid, 200);

    }

}
