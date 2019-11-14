<?php
namespace App\RaidAnalyzer;

use App\Models\Quest;
use App\Helpers\Helpers;
use Illuminate\Support\Facades\Log;

class QuestSearch {

    /**
     *
     */
    function __construct() {
        $this->debug = false;
        $this->query = false;
        $this->quests = Quest::all();
        $this->sanitizedNames = $this->getSanitizedNames();
    }


    /**
     *
     * @return type
     */
    function getSanitizedNames() {
        $names = array();
        foreach( $this->quests as $quest ) {
            $names[] = Helpers::sanitize($pokemon->name);
        }
        return $names;
    }

    /**
     *
     * @param type $query
     * @param type $min
     * @return boolean|\POGO_gym
     */
    function findQuest( $query ) {
        return false;
    }

}
