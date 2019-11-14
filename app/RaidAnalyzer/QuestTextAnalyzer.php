<?php

namespace App\RaidAnalyzer;

use App\RaidAnalyzer\GymSearch;
use App\RaidAnalyzer\PokemonSearch;
use App\RaidAnalyzer\TextAnalyzer;
use Illuminate\Support\Facades\Log;

class QuestTextAnalyzer extends TextAnalyzer {

    function __construct( $source, $guild ) {

        $this->debug = true;

        $this->result = (object) array(
            'gym' => false,
            'quest' => false,
            'reward_type' => false,
            'reward' => false,
            'error' => false,
            'logs' => '',
        );

        $this->start = microtime(true);
        if( $this->debug ) $this->_log('========== Début du traitement '.$source.' ==========');

        $this->text = $source;
        $this->guild = $guild;
        $this->gymSearch = new GymSearch($guild);
        $this->pokemonSearch = new PokemonSearch();

        //Result
        if( $this->isValid() ) {
            $this->run();
        }
    }

    public function isValid() {
        $prefixes = $this->guild->settings->questreporting_text_prefixes;
        if( empty( $prefixes ) ) {
            $this->result->error = 'Aucun préfixe n \'est renseigné';
            return false;
        }
        foreach( $prefixes as $prefix ) {
            $prefix = trim($prefix);
            if( strpos( $this->text, $prefix ) === 0 ) {
                return true;
            }
        }
        $this->result->error = 'Ce texte ne semble pas être une annonce de quête';
        return false;
    }

    public function run() {

        $this->result->gym = $this->gymSearch->findGym($this->text, 70);
        $this->result->reward = $this->pokemonSearch->findPokemon($this->text, 70);
        if( $this->result->reward ) {
            $this->result->reward_type = 'pokemon';
        }
        $this->result->quest = $this->questSearch->findQuest($this->text, 70);
        $time_elapsed_secs = microtime(true) - $this->start;
        if( $this->debug ) $this->_log('========== Fin du traitement '.$this->text.' ('.round($time_elapsed_secs, 3).'s) ==========');
    }

}
