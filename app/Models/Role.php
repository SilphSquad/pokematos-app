<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Guild;
use App\Models\Stop;
use App\Models\RoleCategory;
use RestCord\DiscordClient;
use Illuminate\Support\Facades\Log;

class Role extends Model {

    protected $fillable = [
        'discord_id',
        'guild_id',
        'name',
        'color_type',
        'color',
        'type',
        'gym_id',
        'zone_id',
        'pokemon_id',
        'restricted',
        'category_id',
        'channel_discord_id',
        'message_discord_id'
    ];
    protected $appends = ['category'];
    protected $casts = [
        'restricted' => 'boolean'
    ];

    public function getGuildAttribute() {
        return Guild::find($this->guild_id);
    }

    public function getCategoryAttribute() {
        if( $this->category_id ) {
            $category = RoleCategory::find($this->category_id);
            if( !empty( $category ) ) return $category;
        }
        return false;
    }

    public static function add($args, $fromDiscord = false) {

        $guild = Guild::find($args['guild_id']);
        $roleCategory = false;
        if( !empty( $args['category_id'] ) ) {
            $roleCategory = RoleCategory::find($args['category_id']);
        }

        $color = $args['color'];
        if( $args['color_type'] == 'category' && $roleCategory ) {
            $color = $roleCategory->color;
        }

        if( !$fromDiscord ) {
            $discord = new DiscordClient(['token' => config('discord.token')]);
            $discord_role = $discord->guild->createGuildRole([
                'guild.id' => (int) $guild->discord_id,
                'name' => $args['name'],
                'mentionable' => true,
                'color' => hexdec($color),
            ]);
            $discord_id = $discord_role->id;
        } else {
            $discord_id = $args['discord_id'];
            $args['category_id'] = null;
            $args['type'] = null;
            $args['gym_id'] = null;
            $args['zone_id'] = null;
            $args['pokemon_id'] = null;
        }

        $role = Role::create([
            'discord_id' => $discord_id,
            'guild_id' => $guild->id,
            'category_id' => $args['category_id'],
            'name' => $args['name'],
            'color_type' => $args['color_type'],
            'color' => $color,
            'type' => $args['type'],
            'gym_id' => $args['gym_id'],
            'zone_id' => $args['zone_id'],
            'pokemon_id' => $args['pokemon_id'],
        ]);
        $role->manage_subscription_message(false, $roleCategory);

        return $role;
    }

    public function change($args, $fromDiscord = false) {

        Log::debug( print_r($args, true) );

        $guild = Guild::find($this->guild_id);
        $oldCategory = $this->category;
        $newCategory = false;
        if( !empty( $args['category_id'] ) ) {
            $newCategory = RoleCategory::find($args['category_id']);
        }

        $color = $args['color'];
        if( isset($args['color_type']) && $args['color_type'] == 'category' && $newCategory ) {
            $color = $newCategory->color;
        }

        $type = (isset($args['type'])) ? $args['type'] : $this->type;
        $name = (isset($args['name'])) ? $args['name'] : $this->name;
        $gym_id = (isset($args['gym_id'])) ? $args['gym_id'] : $this->gym_id;
        $zone_id = (isset($args['zone_id'])) ? $args['zone_id'] : $this->zone_id;
        $pokemon_id = (isset($args['pokemon_id'])) ? $args['pokemon_id'] : $this->pokemon_id;
        $category_id = (array_key_exists('category_id', $args)) ? $args['category_id'] : $this->category_id;

        if( !$fromDiscord ) {
            $discord = new DiscordClient(['token' => config('discord.token')]);
            $discord_role = $discord->guild->modifyGuildRole([
                'guild.id' => (int) $this->guild->discord_id,
                'role.id' => (int) $this->discord_id,
                'name' => $name,
                'color' => hexdec($color),
            ]);
        } else {
            $newCategory = $this->category;
            $args['color_type'] = ( $this->category && $color == $this->category->color )  ? 'category' : 'specific' ;
        }

        $this->update([
            'name' => $name,
            'color_type' => $args['color_type'],
            'color' => $color,
            'type' => $type,
            'category_id' => $category_id,
            'gym_id' => $gym_id,
            'zone_id' => $zone_id,
            'pokemon_id' => $pokemon_id,
        ]);
        $this->manage_subscription_message($oldCategory, $newCategory);

        return true;
    }

    public function manage_subscription_message($old_cat, $new_cat) {

        $discord = new DiscordClient(['token' => config('discord.token')]);

        //Si il n'y a pas de catégoie
        if( empty( $old_cat ) && empty( $new_cat ) ) {
            if( !empty($this->channel_discord_id) && !empty($this->message_discord_id)  ) {
                $discord->channel->deleteMessage([
                    'channel.id' => (int) $this->channel_discord_id,
                    'message.id' => (int) $this->message_discord_id,
                ]);
                $this->update([
                    'channel_discord_id' => '',
                    'message_discord_id' => ''
                 ]);
            }
        }

        //Si la catégorie n'a pas changé
        elseif(  $old_cat == $new_cat ) {
            if( !empty($this->channel_discord_id) && !empty($this->channel_discord_id) ) {
                $discord->channel->editMessage([
                    'channel.id' => (int) $this->channel_discord_id,
                    'message.id' => (int) $this->message_discord_id,
                    'content' => '<@&'.$this->discord_id.'>'
                ]);
            }
        }

        //Si il n'y avait pas de catégorie avant et qu'il y en a une maintenant
        elseif( empty( $old_cat ) && !empty( $new_cat ) ) {
            if( $new_cat->notifications ) {
                $message = $discord->channel->createMessage([
                    'channel.id' => (int) $new_cat->channel_discord_id,
                    'content' => '<@&'.$this->discord_id.'>'
                ]);
                $discord->channel->createReaction([
                    'channel.id' => (int) $new_cat->channel_discord_id,
                    'message.id' => (int) $message['id'],
                    'emoji' => '✅'
                ]);
                $this->update([
                    'channel_discord_id' => $new_cat->channel_discord_id,
                    'message_discord_id' => $message['id']
                 ]);
            }
        }

        //Si il y avait une catégorie avant et plus maintenant
        elseif( !empty( $old_cat ) && empty( $new_cat ) ) {
            if( !empty($this->channel_discord_id) && !empty($this->message_discord_id)  ) {
                $discord->channel->deleteMessage([
                    'channel.id' => (int) $this->channel_discord_id,
                    'message.id' => (int) $this->message_discord_id,
                ]);
                $this->update([
                    'channel_discord_id' => '',
                    'message_discord_id' => ''
                 ]);
            }
        }

        elseif( !empty( $old_cat ) && !empty( $new_cat ) ) {
            if( !empty($this->channel_discord_id) && !empty($this->message_discord_id)  ) {
                $discord->channel->deleteMessage([
                    'channel.id' => (int) $this->channel_discord_id,
                    'message.id' => (int) $this->message_discord_id,
                ]);
            }
            if( $new_cat->notifications ) {
                $message = $discord->channel->createMessage([
                    'channel.id' => (int) $new_cat->channel_discord_id,
                    'content' => '<@&'.$this->discord_id.'>'
                ]);
                $discord->channel->createReaction([
                    'channel.id' => (int) $new_cat->channel_discord_id,
                    'message.id' => (int) $message['id'],
                    'emoji' => '✅'
                ]);
                $this->update([
                    'channel_discord_id' => $new_cat->channel_discord_id,
                    'message_discord_id' => $message['id']
                 ]);
            }
        }
    }

    public function suppr($fromDiscord = false) {

        if( !$fromDiscord ) {
            $discord = new DiscordClient(['token' => config('discord.token')]);
            $discord->guild->deleteGuildRole([
                'guild.id' => (int) $this->guild->discord_id,
                'role.id' => (int) $this->discord_id
            ]);
            if( !empty( $this->channel_discord_id ) && !empty( $this->message_discord_id ) ) {
                $discord->channel->deleteMessage([
                    'channel.id' => (int) $this->channel_discord_id,
                    'message.id' => (int) $this->message_discord_id,
                ]);
            }
        }

        Role::destroy($this->id);

        return true;
    }

}
