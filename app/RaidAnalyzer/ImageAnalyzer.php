<?php

namespace App\RaidAnalyzer;

use App\RaidAnalyzer\GymSearch;
use App\RaidAnalyzer\Coordinates;
use App\RaidAnalyzer\ColorPicker;
use App\RaidAnalyzer\MicrosoftOCR;
use App\RaidAnalyzer\PokemonSearch;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;

class ImageAnalyzer {

    function __construct( $source, $guild, $user = false, $channel_discord_id = false ) {

        $this->debug = true;

        $this->guild = $guild;
        $this->user = $user;
        $this->channel_discord_id = $channel_discord_id;
        $this->result = (object) array(
            'type' => false,
            'gym' => false,
            'gym_probability' => 0,
            'eggLevel' => false,
            'pokemon'   => false,
            'pokemon_probability' => 0,
            'date' => false,
            'error' => false,
            'logs' => '',
        );

        $this->start = microtime(true);
        if( $this->debug ) $this->_log('========== Début du traitement '.$source.' ==========');

        $this->gymSearch = new GymSearch($guild);
        $this->pokemonSearch = new PokemonSearch();
        $this->colorPicker = new ColorPicker();
        $this->MicrosoftOCR = new MicrosoftOCR();
        $this->imageData = $this->saveImage( $source );
        $this->coordinates = new Coordinates($this->imageData->width, $this->imageData->height);
        $this->imageData->type = $this->getImageType();

        //Result
        $this->run();
    }

    private function _log( $text, $extra = '' ) {
        if( is_array( $text ) ) {
            Log::debug( print_r($text, true) );
        } else {
            $this->result->logs .= "{$text}\r\n";
            Log::debug( $text );
        }
    }

    public function run() {

         if( $this->imageData->type == 'egg' ) {
             $this->ocr = $this->MicrosoftOCR->read( $this->imageData->url );
             $this->_log($this->ocr);
             $this->result->type = 'egg';
             $this->result->gym = $this->getGym();
             $this->result->date = $this->getTime();
             $this->result->eggLevel = $this->getEggLevel();
         }

        elseif( $this->imageData->type == 'pokemon' ) {
             $this->ocr = $this->MicrosoftOCR->read( $this->imageData->url );
             $this->_log($this->ocr);
             $this->result->type = 'pokemon';
             $this->result->date = $this->getTime();
             $this->result->pokemon = $this->getPokemon();
             $this->result->gym = $this->getGym();
             if( $this->result->pokemon ) {
                 Log::debug( print_r($this->result->pokemon->boss_level, true) );
                 $this->result->eggLevel = $this->result->pokemon->boss_level;
             }
         }

         elseif( $this->imageData->type == 'ex' ) {
             $this->ocr = $this->MicrosoftOCR->read( $this->imageData->url );
             $this->_log($this->ocr);
             $this->result->type = 'ex';
             $this->result->gym = $this->getExGym();
             $this->result->date = $this->getExTime();
             $this->result->eggLevel = 6;
         }

         if( $this->user ) {
             $this->addLog();
         }

         $time_elapsed_secs = microtime(true) - $this->start;
         if( $this->debug ) $this->_log('========== Fin du traitement '.$this->imageData->source.' ('.round($time_elapsed_secs, 3).'s) ==========');
    }

    /**
     *
     * @param type $souce
     * @return boolean
     */
    private function saveImage( $source ) {

        $filename = 'capture-' . time();
        $path = storage_path('app/public/captures/'.$filename.'.jpg');
        $url = env('APP_URL').Storage::url('captures/'.$filename.'.jpg');

        //Create Img from file
        if( strstr($source, '.jpg') ) {
            if( $this->debug ) $this->_log('Img extension : JPG');
            $image = imagecreatefromjpeg($source);
        } elseif( strstr($source, '.jpeg') ) {
            if( $this->debug ) $this->_log('Img extension : JPEG');
            $image = imagecreatefromjpeg($source);
        } elseif( strstr($source, '.png') ) {
            if( $this->debug ) $this->_log('Img extension : PNG');
            $image = imagecreatefrompng($source);
        } else {
            $this->result->error = 'Format de de fichier non accepté';
            return false;
        }

        //If image has android bar
        $firtPixel = $this->getFirstPixel($image);
        $lastPixel = $this->getLastPixel($image);
        if( $this->debug ) $this->_log( 'First pixel : ' . $firtPixel );
        if( $this->debug ) $this->_log( 'Last pixel : ' . $lastPixel );
        if( $firtPixel > 1 || $lastPixel < (imagesy($image) - 1) ) {
            if( $this->debug ) $this->_log( 'Image has android bar. Crop to get needed size' );
            $image = $this->cropImage($image, $firtPixel, $lastPixel);
        }

        //Return data
        $imageData = (object) array(
            'source'   => $source,
            'filename'  => $filename,
            'path'  => $path,
            'url'   => $url,
            'width' => imagesx($image),
            'height' => imagesy($image),
        );

        $ratio = $imageData->width / $imageData->height;
        if( $this->debug ) $this->_log('Img ratio : '.$ratio);

        //Destroy temp Img
        imagejpeg($image, $path);
        imagedestroy($image);
        return $imageData;

    }

    /**
     *
     * @param type $image
     * @return int
     */
    private function getFirstPixel( $image ) {
        $height = imagesy($image);
        $partage_decran = false;
        for($y = 0;$y < $height;$y += 1) {

            //Get the color of the pixel
            $rgb = imagecolorsforindex($image, imagecolorat($image, 2, $y));
            // get the closest color from palette
            if( $rgb['red'] < 3 && $rgb['blue'] < 3 & $rgb['green'] < 3 ) {
                continue;
            }
            if( $rgb['red'] == 36 && $rgb['green'] == 132 & $rgb['blue'] == 232 ) {
                $partage_decran = true;
                continue;
            }
            return ( $partage_decran ) ? $y / 2 : $y ;
        }

        return 0;
    }


    /**
     *
     * @param type $image
     * @return int
     */
    private function getLastPixel( $image ) {
        $height = imagesy($image);
        for($y = $height;$y > 0;$y -= 1) {

            //Get the color of the pixel
            $rgb = imagecolorsforindex($image, imagecolorat($image, 2, $y - 1));
            // get the closest color from palette
            if( $rgb['red'] == 0 && $rgb['blue'] == 0 & $rgb['green'] == 0 ) {
                continue;
            }
            if( $rgb['red'] == 36 && $rgb['green'] == 132 & $rgb['blue'] == 232 ) {
                continue;
            }
            if( $rgb['red'] > 230 && $rgb['red'] == $rgb['blue'] && $rgb['red'] == $rgb['green']  ) {
                continue;
            }

            return $y;
        }

        return 0;
    }

    /**
     *
     * @param type $image
     * @param type $firstPixel
     * @param type $lastPixel
     * @return type
     */
    private function cropImage( $image, $firstPixel, $lastPixel ) {
        $image2 = imagecrop($image, ['x' => 0, 'y' => $firstPixel, 'width' => imagesx($image), 'height' => $lastPixel - $firstPixel]);
        if ($image2 !== FALSE) {
            imagedestroy($image);
            return $image2;
        }
    }


    private function getImageType() {

        $image = imagecreatefromjpeg($this->imageData->path);

        //Check for raidex
        if( $this->debug ) $this->_log('---------- Check if image is Raid EX invit ----------');
        $matching_points = 0;
        foreach( $this->coordinates->forImgTypeEx() as $coords ) {
            $rgb = $this->colorPicker->pickColor( $image, $coords->x, $coords->y );
            if( $this->colorPicker->isExBackground( $rgb ) ) {
                $matching_points++;
            }
        }
        if( $matching_points == 4 ) {
            if( $this->debug ) $this->_log('Great ! Img seems to be an EX invit');
            return 'ex';
        }


        //Check for Future Raid
        if( $this->debug ) $this->_log('---------- Check if image is Raid UserAction ----------');
        $rgb = $this->colorPicker->pickColor( $image, $this->coordinates->forImgTypeEgg()->x, $this->coordinates->forImgTypeEgg()->y );
        if( $this->colorPicker->isFutureTimerColor( $rgb ) ) {
            if( $this->debug ) $this->_log('Great ! Img seems to include an egg');
            return 'egg';
        }

        //Check for active Raid - v1
        if( $this->debug ) $this->_log('IMG does not seem to be an egg. Trying to check if it includes a pokemon');
        $rgb = $this->colorPicker->pickColor( $image, $this->coordinates->forImgTypePokemon()->x, $this->coordinates->forImgTypePokemon()->y );
        if( $this->colorPicker->isActiveTimerColor($rgb) ) {
            if( $this->debug ) $this->_log('Great ! Img seems to include a pokemon');
            return 'pokemon';
        }

        //else
        $this->result->error = 'L\'image n\'a pas été comprise comme une image de raid';
        imagedestroy($image);
        return false;
    }

    private function getEggLevel() {
        $egg_level = 0;
        $image = imagecreatefromjpeg($this->imageData->path);

        if( $this->debug ) $this->_log('---------- Egg level Extraction ----------');
		foreach( array(5,4,3,2,1) as $egglevel ) {
			$count_egg_level = 0;
			foreach( $this->coordinates->forEggLevel() as $coor ) {
				if( !in_array($egglevel, $coor->lvl) ) {
					continue;
				}
				$rgb = $this->colorPicker->pickColor($image, $coor->x, $coor->y );
				if( $this->colorPicker->isEgglevelColor($rgb) ) {
					$count_egg_level += 1;
					if( $this->debug ) $this->_log('Pixel matches');
				} else {
					if( $this->debug ) $this->_log('Pixel does not match');
				}
			}

			if( $this->debug ) $this->_log($count_egg_level . ' matching pixels, '.$egglevel.' expected');

			if( $egglevel === $count_egg_level ) {
				imagedestroy($image);
				$egglevel = $egglevel;
				return $egglevel;
			}
		}

        imagedestroy($image);
        $this->result->error = "Le niveau du raid n'a pas été trouvé";
        return false;
    }

    function getExTime() {
        $date = false;
        foreach( $this->ocr as $line ) {
            if( preg_match( '/[0-9][0-9]:[0-9][0-9] - [0-9][0-9]:[0-9][0-9]/i', $line ) ) {
                $date_els = explode(' - ', $line);
                $date = $date_els[0];
            }
        }

        if( $date ) {
            $date_els = explode(' ', $date);
            $year = date('Y');
            $day = ( strlen($date_els[0]) === 1 ) ? '0'.$date_els[0] : $date_els[0] ;
            $month = str_replace(
                ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'aout', 'septembre', 'octobre', 'novembre', 'décembre'],
                ['01', '02', '03', '04', '05', '06', '07', '08', '08', '09', '10', '11', '12'],
                $date_els[1]
            );
            $minutes = $date_els[2];
            return "{$year}-{$month}-{$day} {$minutes}:00";
        }

        return false;
    }

    function getTime() {
        $minutes = false;
        foreach( $this->ocr as $line ) {
            if( preg_match( '/^[0-9]:[0-9][0-9]:[0-9][0-9]/i', $line ) ) {
                $timer = explode(':', $line);
                $minutes = $timer[1];
            }
        }

        if( $minutes ) {
            $date = new \DateTime();
            $date->setTimezone(new \DateTimeZone('Europe/Paris'));
            if( $this->imageData->type == 'egg' ) {
                $date->modify('+'.$minutes.' minutes');
            } else {
                $minutes = 45 - $minutes;
                $date->modify('-'.$minutes.' minutes');
            }
            return $date->format('Y-m-d H:i:s');
        }

        $this->result->error = "Aucun timing trouvé dans la capture";
        return false;
    }

    function getGym() {
        $result = $this->gymSearch->findGym($this->ocr, $this->guild->settings->raidreporting_gym_min_proability);
        if( $result ) {
            if( $this->debug ) $this->_log('Gym finded in database : ' . $result->gym->name . '('.$result->probability.'%)' );
            $this->result->gym_probability = $result->probability;
            return $result->gym;
        }
        if( $this->debug ) $this->_log('Nothing found in database :(' );
        $this->result->error = "L'arène n'a pas été trouvée";
        return false;
    }

    function getExGym() {
        $value = implode(' ', $this->ocr);
        $result = $this->gymSearch->findGymFromString($value, $this->guild->settings->raidreporting_gym_min_proability);
        if( $result ) {
            if( $this->debug ) $this->_log('Gym finded in database : ' . $result->gym->name . '('.$result->probability.'%)' );
            $this->result->gym_probability = $result->probability;
            return $result->gym;
        }
        if( $this->debug ) $this->_log('Nothing found in database :(' );
        $this->result->error = "L'arène n'a pas été trouvée";
        return false;
    }

    function getPokemon() {
        $cp = $this->MicrosoftOCR->cp_line;
        $result = $this->pokemonSearch->findPokemon($this->ocr, $cp, 90);
        if( $result ) {
            if( $this->pokemonSearch->num_line ) {
                unset($this->ocr[$this->pokemonSearch->num_line]);
                $this->ocr = array_values($this->ocr);
            }
            if( $this->debug ) $this->_log('Pokemon finded in database : ' . $result->pokemon->name_fr . '('.$result->probability.'%)' );
            $this->result->pokemon_probability = $result->probability;
            return $result->pokemon;
        }

        if( $this->debug ) $this->_log('Nothing found in database :(' );
        $this->result->error = "Aucun Pokémon trouvé";
        return false;

    }

    public function addLog() {

        //Construction du tableau
        $success = ( $this->result->error ) ? false : true;
        $result = [
            'type' => $this->result->type,
            'gym' => $this->result->gym,
            'gym_probability' => $this->result->gym_probability,
            'date' => $this->result->date,
            'pokemon' => $this->result->pokemon,
            'pokemon_probability' => $this->result->pokemon_probability,
            'egg_level' => $this->result->eggLevel,
            'url' => $this->imageData->url,
            'ocr' => (property_exists($this, 'ocr')) ? implode(' ', $this->ocr) : '',
        ];

        //Ajout du log
        \App\Models\Log::create([
            'city_id' => $this->guild->city->id,
            'guild_id' => $this->guild->id,
            'type' => 'analysis-img',
            'success' => $success,
            'error' => $this->result->error,
            'source_type' => 'img',
            'source' => $this->imageData->source,
            'result' => $result,
            'user_id' => ( $this->user ) ? $this->user->id : 0,
            'channel_discord_id' => $this->channel_discord_id
        ]);
    }

}
