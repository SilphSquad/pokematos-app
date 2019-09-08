import Vue from 'vue';
import Vuex from 'vuex';
import axios from 'axios';
import moment from 'moment';
import VuexPersistence from 'vuex-persist';

Vue.use(Vuex);

const defaultSettings = {
    raidsListFilters: ["1","2","3","4","5","6"],
    raidsListOrder: 'date',
    hideGyms: false
}

const store = new Vuex.Store({
    state: {
        currentCity: JSON.parse(localStorage.getItem('pokematos_currentCity') ),
        cities: JSON.parse(localStorage.getItem('pokematos_cities') ),
        gyms: JSON.parse(localStorage.getItem('pokematos_gyms') ),
        pokemons: JSON.parse(localStorage.getItem('pokematos_pokemons') ),
        quests: JSON.parse(localStorage.getItem('pokematos_quests') ),
        settings: JSON.parse(localStorage.getItem('pokematos_settings') ),
        user: JSON.parse(localStorage.getItem('pokematos_user') ),
        zones: JSON.parse(localStorage.getItem('pokematos_zones') ),
        snackbar: false,
    },
    mutations: {
        fetchGyms( state ) {
            axios.get('/api/user/cities/'+state.currentCity.id+'/gyms').then( res => {
                state.gyms = res.data;
                localStorage.setItem('pokematos_gyms', JSON.stringify(state.gyms));
            }).catch( err => {
                //No error
            });
        },
        fetchRaids( state, payload ) {
            if( payload ) {
                state.snackbar = {
                    message: 'Synchronisation en cours',
                    timeout: 10000
                }
            }
            axios.get('/api/user/cities/'+state.currentCity.id+'/active-gyms').then( res => {
                let gyms = state.gyms
                res.data.forEach( function(gym){
                    let objIndex = gyms.findIndex((obj => obj.id == gym.id));
                    if( objIndex ) {
                        console.log('MAJ du POI '+gym.name)
                        gyms[objIndex] = gym;
                        state.gyms = [gym];
                    }
                });
                state.gyms = gyms;
                localStorage.setItem('pokematos_gyms', JSON.stringify(state.gyms));
                if( payload ) {
                    state.snackbar = {
                        message: 'Synchronisation terminée',
                        timeout: 1000
                    }
                }
            }).catch( err => {
                console.log(err)
                if( payload ) {
                    state.snackbar = {
                        message: 'Erreur de synchronisation',
                        timeout: 1000
                    }
                }
            });
        },
        fetchPokemon( state ) {
            axios.get('/api/pokemons').then( res => {
                state.pokemons = res.data;
                localStorage.setItem('pokematos_pokemons', JSON.stringify(state.pokemons));
            }).catch( err => {
                //No error
            });
        },
        fetchQuests( state ) {
            axios.get('/api/quests').then( res => {
                state.quests = res.data;
                localStorage.setItem('pokematos_quests', JSON.stringify(state.quests));
            }).catch( err => {
                //No error
            });
        },
        fetchZones( state ) {
            axios.get('/api/user/cities/'+state.currentCity.id+'/zones').then( res => {
                state.zones = res.data;
                localStorage.setItem('pokematos_zones', JSON.stringify(state.zones));
            }).catch( err => {
                //No error
            });
        },
        fetchCities( state ) {
            axios.get('/api/user/cities/').then( res => {
                state.cities = res.data;
                localStorage.setItem('pokematos_cities', JSON.stringify(state.cities));
                if (!state.currentCity || state.currentCity == undefined) {
                    state.currentCity = state.cities[0];
                    localStorage.setItem('pokematos_currentCity', JSON.stringify(state.cities[0]));
                } else {
                    var newCurrentCity = res.data.find(function(city) {
                      return city.id == state.currentCity.id;
                    });
                    if( newCurrentCity ) {
                        state.currentCity = newCurrentCity;
                        localStorage.setItem('pokematos_currentCity', JSON.stringify(newCurrentCity));
                    } else {
                        state.currentCity = state.cities[0];
                        localStorage.setItem('pokematos_currentCity', JSON.stringify(state.cities[0]));
                    }

                }
            }).catch( err => {
                //No error
            });
        },
        fetchUser( state ) {
            axios.get('/api/user').then( res => {
                state.user = res.data
                localStorage.setItem('pokematos_user', JSON.stringify(res.data));
            }).catch( err => {
                //No error
            });
        },
        setCity( state, payload ) {
            state.currentCity = payload.city;
            localStorage.setItem('pokematos_currentCity', JSON.stringify(payload.city));
        },
        setPokemons( state, payload ) {
            state.pokemons = payload;
            localStorage.setItem('pokematos_pokemons', JSON.stringify(payload));
        },
        setSetting( state, payload ) {
            if( state.settings === undefined || !state.settings || state.settings === null ) state.settings = {};
            state.settings[payload.setting] = payload.value;
            localStorage.setItem('pokematos_settings', JSON.stringify(state.settings));
        },
        initSetting( state, payload ) {
            if( state.settings === undefined || !state.settings || state.settings === null ) state.settings = {};
            if( state.settings[payload.setting] ) {
                return;
            } else {
                state.settings[payload.setting] = payload.value;
                localStorage.setItem('pokematos_settings', JSON.stringify(state.settings));
            }
        },
        setSnackbar( state, payload ) {
            state.snackbar = payload;
        },
        deletePOIActivity( state, payload ) {
            let objIndex = state.gyms.findIndex((obj => obj.id == payload.id));
            let gym = state.gyms[objIndex];
            gym.raid = false;
            gym.quest = false;
            state.gyms = [
                ...state.gyms.filter(element => element.id !== gym.id),
                gym
            ];
            localStorage.setItem('pokematos_gyms', JSON.stringify(state.gyms));
        },
    },
    getters: {
        activeRaids: state => {
            if( !state.gyms || state.gyms.length === 0 ) return [];
            return state.gyms.filter((gym) => {
                var now = moment();
                return gym.raid && now.isAfter(gym.raid.start_time) && now.isBefore(gym.raid.end_time);
            });
        },
        futureRaids: state => {
            if( !state.gyms || state.gyms.length === 0 ) return [];
            return state.gyms.filter((gym) => {
                var now = moment();
                if (gym.raid) {
                    var startTime = moment(gym.raid.start_time, '"YYYY-MM-DD HH:mm:ss"');
                }
                return gym.raid && startTime.isAfter(now);
            });
        },
        activeQuests: state => {
            if( !state.gyms || state.gyms.length === 0 ) return [];
            return state.gyms.filter((gym) => {
                return (gym.quest);
            });
        },
        rewardQuests: state => {
            if( !state.quests || state.quests.length === 0 ) return [];
            let rewardQuests = [];
            state.quests.forEach(function(quest) {
                if( quest.rewards ) {
                    quest.rewards.forEach(function(reward) {
                        if( !rewardQuests.includes(reward) && !reward.pokedex_id ) {
                            rewardQuests.push(reward);
                        }
                    });
                }
            });
            return rewardQuests;
        },
        pokemonQuests: state => {
            if( !state.quests || state.quests.length === 0 ) return [];
            let pokemonQuests = [];
            state.quests.forEach(function(quest) {
                if( quest.rewards ) {
                    quest.rewards.forEach(function(reward) {
                        if( pokemonQuests.filter(item => (item.id == reward.id)).length === 0 && reward.pokedex_id ) {
                            pokemonQuests.push(reward);
                        }
                    });
                }
            });
            return pokemonQuests.sort(function(a, b){
                if(a.name < b.name) { return -1; }
                if(a.name > b.name) { return 1; }
                return 0;
            })
        },
        getRaidBosses:state => {
            if( !state.pokemons || state.pokemons.length === 0 ) return [];
            return state.pokemons.filter((pokemon) => {
                return pokemon.boss == true && pokemon.boss_level >= 0 && pokemon.boss_level <= 5;
            });
        },
        getSetting: state => (setting) => {
            if( state.settings && state.settings[setting] ) {
                return state.settings[setting];
            } else {
                return false;
            }
        },
    },
    actions: {
        autoFetchData ({ commit }) {
            commit('fetchRaids')
            commit('fetchPokemon')
            commit('fetchQuests')
        },
        fetchData ({ commit }) {
            commit('fetchRaids', true)
            commit('fetchZones')
        },
        changeCity ({ dispatch, commit }, payload) {
            commit('setCity', payload)
            dispatch('fetchData');
        },
    },
});

export default store;
