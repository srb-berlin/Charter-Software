<?php
namespace App\Model\Table;

use App\Model\Entity\Flight;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Log\LogTrait;
use Cake\ORM\TableRegistry;

/**
 * Flights Model
 */
class FlightsTable extends Table
{

    private $flight;
    private $unavailableReasons;
    private $inputError;

    use LogTrait;
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        $this->table('flights');
        $this->displayField('id');
        $this->primaryKey('id');
        $this->belongsTo('Customers', [
            'foreignKey' => 'customer_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('Planes', [
            'foreignKey' => 'plane_id',
            'joinType' => 'INNER'
        ]);
        $this->hasMany('Invoices', [
            'foreignKey' => 'flight_id'
        ]);
        $this->belongsToMany('Airports', [
            'foreignKey' => 'flight_id',
            'targetForeignKey' => 'airport_id',
            'joinTable' => 'airports_flights'
        ]);
        $this->belongsToMany('Users', [
            'foreignKey' => 'flight_id',
            'targetForeignKey' => 'user_id',
            'joinTable' => 'users_flights'
        ]);


    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->add('id', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('id', 'create');

        $validator
            ->allowEmpty('flight_number');

        $validator
            ->add('start_date', 'valid', ['rule' => 'datetime'])
            ->allowEmpty('start_date');

        $validator
            ->add('end_date', 'valid', ['rule' => 'datetime'])
            ->allowEmpty('end_date');

        $validator
            ->add('status', 'valid', ['rule' => 'numeric'])
            ->requirePresence('status', 'create')
            ->notEmpty('status');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['customer_id'], 'Customers'));
        $rules->add($rules->existsIn(['plane_id'], 'Planes'));
        return $rules;
    }

    public function saveFlight(){

        $customer = $this->Customers->createDummyCustomer();

        $planeType = $this->getUsedPlaneTypeByFlight();

        $flight = $this->newEntity();
        $flight['flight_number'] = 'XXX-'.rand(10000000,99999999).'-F';
        $flight['customer_id'] = $customer['id'];
        $flight['plane_id'] = $this->flight['availablePlane']['id'];
        $flight['start_date'] = $this->flight['startDate'];
        $flight['end_date'] = $planeType['calculatedEndDate'];
        $flight['status'] = FLIGHT_DUMMY;
        $flight['cost_effectiv_travel_time'] = $planeType['costEffectivTravellTime'];

        $flight = $this->save($flight);
        $this->flight['databaseObject'] = $flight;



        $airportsFlights = TableRegistry::get('AirportsFlights');
        $usersFlights = TableRegistry::get('UsersFlights');

//)->modify('+'.ceil($planeType['travellTime']).' hour')->
        $departuresTime = (new \DateTime($this->flight['startDate']))->format('Y-m-d H:i:s');
        $arrivalTime = '';

        $order = 0;
        foreach($this->flight['stations'] as $key => $station){


            // AirportsFlights füllen
            $airport = $this->Airports->find()->where(['airport_name' => $this->flight['airport'][$key] , 'country' => $this->flight['country'][$key]])->first();
            $stayDuration = (isset($this->flight['stayDuration'][$key-1]))? $this->flight['stayDuration'][$key-1] : 0 ;
            $flightTime = (isset($station['passedDistance']))? ceil(($station['passedDistance']/$planeType['speed'])*60):0; // flugzeit zur Station in Minuten

            $stationData = $airportsFlights->newEntity();
            $stationData['flight_id'] = $flight['id'];
            $stationData['airport_id'] = $airport->id;
            $stationData['flight_time'] = $flightTime;
            $stationData['stay_duration'] = $stayDuration;
            $stationData['order_number'] = $order;
            $order++;
            $airportsFlights->save($stationData);

            // für anzeige


            if(isset($this->flight['stations'][$key-1])){ // gibt es einen Flughafen zuvor?
                $arrivalTimeDummy = (new \DateTime($this->flight['stations'][$key-1]['departuresTime']))->modify('+'.$flightTime.' minutes');
                $arrivalTime = $arrivalTimeDummy->format('Y-m-d H:i:s');

                if(isset($this->flight['stations'][$key+1])){
                    $departuresTime =  $arrivalTimeDummy->modify('+'.$stayDuration.' hour')->format('Y-m-d H:i:s');
                }else{
                    $departuresTime = '';
                }
            }

            $this->flight['stations'][$key]['arrivalTime'] = $arrivalTime;
            $this->flight['stations'][$key]['flightTime'] = $flightTime;
            $this->flight['stations'][$key]['stayDuration'] = $stayDuration;
            $this->flight['stations'][$key]['passedDistance'] = (isset($station['passedDistance']))? $station['passedDistance']: 0;
            $this->flight['stations'][$key]['airport'] = $this->flight['airport'][$key];
            $this->flight['stations'][$key]['country'] = $this->flight['country'][$key];
            $this->flight['stations'][$key]['departuresTime'] = $departuresTime;

            // für den nächsten Flughafen Ankunftzeit

        }

        // UsersFlights füllen
        $i = 0;
        if(isset($planeType['crew']['copilot'])){
            foreach($planeType['crew']['pilot'] as $pilot){
                $usersFlightsData = $usersFlights->newEntity();
                $usersFlightsData['flight_id'] = $flight['id'];
                $usersFlightsData['user_id'] = $pilot['id'];
                $usersFlights->save($usersFlightsData);
            }
        }
        if(isset($planeType['crew']['copilot'])){
            foreach($planeType['crew']['copilot'] as $copilot){
                $usersFlightsData = $usersFlights->newEntity();
                $usersFlightsData['flight_id'] = $flight['id'];
                $usersFlightsData['user_id'] = $copilot['id'];
                $usersFlights->save($usersFlightsData);
            }
        }
        if(isset($planeType['crew']['attendants'])){
            foreach($planeType['crew']['attendants'] as $attendants){
                $usersFlightsData = $usersFlights->newEntity();
                $usersFlightsData['flight_id'] = $flight['id'];
                $usersFlightsData['user_id'] = $attendants['id'];
                $usersFlights->save($usersFlightsData);
            }
        }
        $this->log($usersFlightsData, 'debug');
        $this->log($stationData, 'debug');

        $this->flight['planeType'] = $planeType;

        return $flight;
    }


    public function checkAvailability(){



        if($this->evaluateFlight()){

            // $this->log($this->flight['availablePlane'], 'debug');

            // $this->log($this->flight, 'debug');
            // $this->log($this->unavailableReasons, 'debug');
            return true;
        }else{
            // $this->log($this->flight['availablePlane'], 'debug');
            // $this->log($this->flight, 'debug');
            // $this->log($this->unavailableReasons, 'debug');
            return false;
        }
    }

    public function isValidateInput(){

        $this->inputError = [];
        $return = true;
        $today = date('Y-m-d H:i:s');

        if(isset($this->flight['startDate']) && !empty($this->flight['startDate'])){
            $startDate = (new \DateTime($this->flight['startDate']))->format('Y-m-d H:i:s');

            if($today > $startDate){
                $this->inputError['startDate'] = "Das eingegebene Datum liegt in der Vergangenheit!";
                $return = false;
            }else{
                $this->flight['startDate'] = $startDate;
            }
        }else{
            $this->inputError['startDate'] = "Bitte geben Sie ein Von-Datum ein.";
            $return = false;
        }

        if($this->flight['mode'] == 'classicCharter'){
            foreach($this->flight['country'] as $key => $country){
                if( $country == '0'){ $this->inputError['country'] = "Bitte wählen Sie ein Land aus."; $return = false;}
                if(!isset($this->flight['airport']) || !isset($this->flight['airport'][$key]) || $this->flight['airport'][$key] == '0'){
                    $this->inputError['airport'] = "Bitte wählen Sie einen Flughafen aus.";
                    $return = false;
                }
            }
        }

        if($this->flight['mode'] == 'timeCharter'){

            if(isset($this->flight['endDate']) && !empty($this->flight['endDate'])){
                $endDate = (new \DateTime($this->flight['endDate']))->format('Y-m-d H:i:s');

                if($startDate > $endDate ){
                    $this->inputError['endDate'] = "Das eingegebene Bis-Datum liegt vor dem eingebenen Von-Datum.";
                    $return = false;
                }else{
                    $this->flight['endDate'] = $startDate;
                }
            }else{
                $this->inputError['endDate'] = "Bitte geben Sie ein Bis-Datum ein.";
                $return = false;
            }
        }
        return $return;
    }


    private function evaluateFlight(){
        if($this->flight['mode'] == 'classicCharter') {
            $this->flight = $this->Airports->calculateDistances($this->flight);
            $this->flight['pax'] = $this->flight['pax'] + 1;
        }// bei classic Distanzen berechnen um zeiten berechnen zu können

        $this->setTechnicallyPossiblePlaneTypes(); // genug platz für pax und Reichweite gut genug?
        $this->getPossibleDatesByPlaneType(); // berechnung der Reisezeit anhand der geschwindigkeit, Stay duration, inkl. Termine

        $this->filterPlaneTypesByAvailableCrew(); // ist zum angegebenen Teilpunkt für den jeweiligen Flug genug Cew vorhanden?

        if(!empty($this->flight['technicallyPossiblePlaneTypes'])){

            foreach($this->flight['technicallyPossiblePlaneTypes'] as $key => $planeType ){

                if(isset($this->flight['wishedPlaneID']) && $this->flight['wishedPlaneID'] > 0){

                    if($this->Planes->exists(['plane_type_id' =>$planeType['id'], 'id' =>$this->flight['wishedPlaneID']])){

                        if($planeType['crewAvailable']){
                            $plane = $this->Planes->find()->where(['plane_type_id' =>$planeType['id'], 'id' =>$this->flight['wishedPlaneID']])->first();

                            if(isset($plane) && $this->checkPlanAvailablility($planeType, $plane['id'])){
                                $this->flight['availablePlane'] = $plane;
                                return true;
                            }else{
                                $this->unavailableReasons['dateUnavailable'] = 'Wunschflugzeug zu diesem Termin nicht Verfügbar.';
                                return false;
                            }
                        }else{
                            $this->unavailableReasons['insufficientCrew'] = 'Nicht genügend Personal für Ihr Wunschflugzeug vorhanden.';
                            return false;
                        }
                    }
                }else{

                    if($planeType['crewAvailable']){

                        $planes = $this->Planes->find()->where(['plane_type_id' =>$planeType['id']])->all();

                        foreach($planes as $plane){ //gibt es ein Flugzeug das frei ist?

                            $this->unavailableReasons['dateUnavailable'] = 'Kein Flugzeug zu diesem Termin verfügbar.';
                            if($this->checkPlanAvailablility($planeType, $plane['id'])){

                                unset($this->unavailableReasons['insufficientCrew']);
                                unset($this->unavailableReasons['dateUnavailable']);
                                $this->flight['availablePlane'] = $plane;
                                return true;
                            }
                        }
                    }else{
                        $this->unavailableReasons['insufficientCrew'] = 'Nicht genügend Personal vorhanden.';
                    }
                }
            }
        }else{
            $this->unavailableReasons['technicalUnavailable'] = "Distanz zu weit oder nicht genug Platz.";
        }
        return false;
    }

    private function checkPlanAvailablility( $planeType ,$planesID){
        if( !$this->exists(['start_date <=' => $this->flight['startDate'], 'end_date >=' => $this->flight['startDate'] ,'plane_id' => $planesID]) && //vor
            !$this->exists(['start_date >=' => $this->flight['startDate'], 'end_date <=' => $planeType['calculatedEndDate'] ,'plane_id' => $planesID]) && //mitte
            !$this->exists(['start_date <=' => $planeType['calculatedEndDate'], 'end_date >=' => $planeType['calculatedEndDate'] ,'plane_id' => $planesID]) && //nach
            !$this->exists(['start_date <=' => $this->flight['startDate'], 'end_date >=' => $planeType['calculatedEndDate'] ,'plane_id' => $planesID]) && //umfangend
            !$this->exists(['start_date' => $this->flight['startDate'], 'end_date' => $planeType['calculatedEndDate'] ,'plane_id' => $planesID]) // exakt das selbe Interval
            ){
            return true;
        }
        return false;
    }

    private function filterPlaneTypesByAvailableCrew(){

        // $crewAvailable = false;
        foreach($this->flight['technicallyPossiblePlaneTypes'] as $planeTypeKey => $planeType ){

            $blockedFlights = $this->find()
                ->where(['status' => '1','start_date <=' => $this->flight['startDate'], 'end_date >=' => $this->flight['startDate']])
                ->orWhere( ['AND' => [
                    ['start_date >=' => $this->flight['startDate']], ['end_date <=' => $planeType['calculatedEndDate']]
                ]])
                ->orWhere( ['AND' => [
                    ['start_date <=' => $planeType['calculatedEndDate']], ['end_date >=' => $planeType['calculatedEndDate']]
                ]])
                ->orWhere( ['AND' => [
                    ['start_date <=' => $this->flight['startDate']], ['end_date >=' => $planeType['calculatedEndDate']]
                ]])
                ->orWhere( ['AND' => [
                    ['start_date' => $this->flight['startDate']], ['end_date' => $planeType['calculatedEndDate']]
                ]])
                ->contain(['Users'])
                ->all();

            $blockedUserIds = ['0'=>'999999'];
            foreach($blockedFlights as $blockedFlight){
                foreach($blockedFlight['users'] as $blockedUser){
                    $blockedUserIds[] = $blockedUser->id;
                }
            }
            $availableUsers = $this->Users->find()->where(['Users.id NOT IN' => $blockedUserIds])->contain(['Groups'])->all();

            if($this->checkSufficientCrewByPlanetype($planeTypeKey, $availableUsers)){
                $crewAvailable = true;
                $this->flight['technicallyPossiblePlaneTypes'][$planeTypeKey]['crewAvailable'] = $crewAvailable;
            }
        }
    }

    private function checkSufficientCrewByPlanetype($planeTypeKey, $availableUsers){

        $flightCrewNeeded = $this->flight['technicallyPossiblePlaneTypes'][$planeTypeKey]['flight_crew'];
        $pilotsNeeded = 1;
        $copilotsNeeded = $flightCrewNeeded - 1;

        $crew = ['pilot'=>[],'copilot'=>[],'attendants'=>[]];
        $usedUserIds = [];

        $cabinCrewNeeded = $this->flight['technicallyPossiblePlaneTypes'][$planeTypeKey]['cabin_crew']+$this->flight['additionalAttendants'];

        foreach($availableUsers as $key => $availableUser){

            if($availableUser['group_id'] == 1 && count($crew['pilot']) != $pilotsNeeded){ //pilot
                $crew['pilot'][] = $availableUser;
                $usedUserIds[] = $availableUser->id;
                continue;
            }
            if($availableUser['group_id'] == 2 && count($crew['copilot']) != $copilotsNeeded){ //copilot
                $crew['copilot'][] = $availableUser;
                $usedUserIds[] = $availableUser->id;
                continue;
            }
            if($availableUser['group_id'] == 3 && count($crew['attendants']) != $cabinCrewNeeded){
                $crew['attendants'][] = $availableUser;
                $usedUserIds[] = $availableUser->id;
                continue;
            }
        }

        if(count($crew['copilot']) != $copilotsNeeded){

            foreach($availableUsers as $key => $availableUser){
                if($availableUser['group_id'] == 1 && count([$crew['copilot']]) != $copilotsNeeded && !in_array($availableUser->id, $usedUserIds)){ //copilot
                    $crew['copilot'][] = $availableUser;
                }
            }
        }

        if(count($crew['pilot']) != $pilotsNeeded || count($crew['copilot']) != $copilotsNeeded || count($crew['attendants']) != $cabinCrewNeeded){
              return false;
        }
        $this->flight['technicallyPossiblePlaneTypes'][$planeTypeKey]['crew'] = $crew;
        return true;
    }

    /*
     * Flugzeug Availability checks
     */

    private function getPossibleDatesByPlaneType(){

        if($this->flight['mode'] == 'classicCharter'){

            $this->calculateTravellTimeByPlaneType();

            foreach($this->flight['technicallyPossiblePlaneTypes'] as $key => $planeType){
                unset($end);

                $end = (new \DateTime($this->flight['startDate']))->modify('+'.ceil($planeType['travellTime']).' hour')->format('Y-m-d H:i:s');
                $this->flight['technicallyPossiblePlaneTypes'][$key]['calculatedEndDate'] = $end;
            }

        }elseif($this->flight['mode'] == 'timeCharter'){

            foreach($this->flight['technicallyPossiblePlaneTypes'] as $key => $planeType){
                $this->flight['technicallyPossiblePlaneTypes'][$key]['calculatedEndDate'] = $this->flight['endDate'];
            }
        }
    }

    private function calculateTravellTimeByPlaneType(){

        foreach($this->flight['technicallyPossiblePlaneTypes'] as $key => $planeType){
            $distance = 0;
            $nettoStayDuration = 0;
            $this->flight['technicallyPossiblePlaneTypes'][$key]['costEffectivTravellTime'] = 0;
            foreach($this->flight['stations'] as $stationKey => $station){
                $distance += (isset($station['passedDistance']))?$station['passedDistance']:0;

                if (isset($this->flight['stayDuration'][$stationKey])){
                    if($this->flight['stayDuration'][$stationKey] < 0.75){
                        $nettoStayDuration += 0.75;
                        $this->flight['technicallyPossiblePlaneTypes'][$key]['costEffectivTravellTime'] += 0.75;
                        $this->flight['stayDuration'][$stationKey] = 0.75;
                    }else{
                        $nettoStayDuration += $this->flight['stayDuration'][$stationKey];
                        $this->flight['technicallyPossiblePlaneTypes'][$key]['costEffectivTravellTime'] += 0.75;
                    }
                }
            }
            $this->flight['technicallyPossiblePlaneTypes'][$key]['costEffectivTravellTime'] += ($distance/$planeType['speed']);
            $this->flight['technicallyPossiblePlaneTypes'][$key]['travellTime'] = ($distance/$planeType['speed'])+$nettoStayDuration;

        }
    }


    private function setTechnicallyPossiblePlaneTypes(){


        if($this->flight['mode'] == 'classicCharter'){
            // range weit genug und genug Platz für Passagiere?
            $longestDistance = $this->getLongestDistance($this->flight);
            $this->flight['technicallyPossiblePlaneTypes'] = $this->Planes->PlaneTypes->find()->where(['max_range >=' => $longestDistance, 'pax >=' => $this->flight['pax']])->order(['speed'=>'DESC'])->all()->toArray();
        }elseif($this->flight['mode'] == 'timeCharter'){
            // alle Flugzeugtypen für die Charter Dauer
            $this->flight['technicallyPossiblePlaneTypes'] = $this->Planes->PlaneTypes->find()->all()->toArray();
        }

    }

    private function getLongestDistance($flight){
        $longestDistance = 0;
        foreach($flight['stations'] as $stations){

            if(isset($stations['passedDistance'])  && $longestDistance < $stations['passedDistance']){
                $longestDistance = $stations['passedDistance'];
            }
        }
        return $longestDistance;
    }



    private function getUsedPlaneTypeByFlight(){

        foreach($this->flight['technicallyPossiblePlaneTypes'] as $key => $planeType){
            if($planeType->id == $this->flight['availablePlane']['plane_type_id']){
                return $planeType;
            }
        }
    }

    public function setFlight($data){ $this->flight = $data; }
    public function getFlight(){ return $this->flight; }
    public function getInputError(){ return $this->inputError;}
    public function getUnavailableReasons(){ return $this->unavailableReasons;}
}
