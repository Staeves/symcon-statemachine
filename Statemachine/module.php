<?

class Statemachine extends IPSModule {
	// Overrides the internal IPS_Create($id) function
	public function Create(): void {
		// Don't delete this line
		parent::Create();
		
		// use atributes, so that we can alter and format them as we want
		$this->RegisterAttributeString("devices", "[]");	// json encoded list of devices that are part of this Statemachine
		$this->RegisterAttributeString("states", "[]");		// json encoded list of states each is [<id> => ["name"=> string, "values" => [<instanceID> => Value]]]

		// we need the prperty or the apply button will never show up if the list has a name :(
		$this->RegisterPropertyString("devicesList", "[]");
		$this->RegisterPropertyString("statesList", "[]");
	}

	// dynamic configurationform
	public function GetConfigurationForm () : string {
		$res = ["elements" => [
			["type" => "Label", "caption" => "Bitte alle Änderungen übernehmen, bevor Sie die nächste Liste ausfüllen"],
			$this->addDevicesButtonForm(),
			$this->devicesListForm(),
			$this->statesListForm(),
			$this->stategroupsListForm(),
			$this->triggerListFrom(),
			$this->transitionsListForm()
		]];
		$json = json_encode($res);
		/*echo $json;*/
		return $json;
	}

	// Overwrites the internal IPS_ApplyChanges($id) function
	public function ApplyChanges(): void {
		// Don't delete this line
		parent::ApplyChanges();

		// device list
		$rawIDs = json_decode($this->ReadPropertyString("devicesList"), true);
		$convertedIDs = array_map(function ($x) {return $x["deviceID"];}, $rawIDs);
		$this->WriteAttributeString("devices", json_encode($convertedIDs));

		// state List
		$stateData = json_decode($this->ReadPropertyString("statesList"), true);
		$convertStates = function($x) use($convertedIDs) {
			$filteredStateValues = array_filter($x["stateValues"], function ($y) use($convertedIDs) {return in_array($y["deviceID"], $convertedIDs);});
			$valuesArray = array_combine(
				array_map(function ($y) {return $y["deviceID"];}, $filteredStateValues),
				array_map(function ($y) {return $y["devValue"];}, $filteredStateValues)
			);
			return [ "name" => $x["stateName"], "values" => $valuesArray];
		};
		$getKeys = function ($x) {
			return $x["stateID"];
		};
		$convertedStates = array_combine(array_map($getKeys, $stateData), array_map($convertStates, $stateData));
		$this->WriteAttributeString("states", json_encode($convertedStates));


		// Reload the form to make shre it is updated
		$this->ReloadForm();
	}

	public function RequestAction ($Ident, $Value) : void {
	}

	public function AddDevices(int $parentID) : void {
		// add all VirtDev devices that are in the category $parentID
		$newDevices = array_filter(IPS_GetChildrenIDs($parentID), function($x) {return IPS_GetObject($x)["ObjectType"] == 1 && IPS_GetInstance($x)["ModuleInfo"]["ModuleID"] == "{5FC7B1D7-ED60-B72C-EA50-A8135F4E387A}";});
		$devices = array_unique(array_merge(json_decode($this->ReadAttributeString("devices")), $newDevices));
		// update devices list in the settings
		$this->UpdateFormField("devicesList", "values", json_encode($this->devicesAsListValues($devices)));
	}
	public function UpdateNextStateListIndex(mixed $states) : void {
		$maxIndex = empty($states) ? 0 : max(array_map(function ($x) {return $x["stateID"];}, iterator_to_array($states)));
		$this->UpdateFormField("statesList", "columns.0.add", $maxIndex+1);
	}

	/* 
	 * private Form functions
	 */
	private function addDevicesButtonForm() : array {
		return [
			"type" => "PopupButton",
			"caption" => "Alle Geräte einer Kategorie hinzufügen",
			"popup" => [
				"buttons" => [[
					"caption" => "Hinzufügen",
					"onClick" => [
						'StateM_AddDevices($id, $CategoryToAdd);'
					]
				]],
				"caption" => "Alle Geräte einer Kategorie zum Zustandsautomaten hinzufügen",
				"items" => [[
					"type" => "SelectCategory",
					"caption" => "Kategorie",
					"name" => "CategoryToAdd"
				]]
			]
		];
	}
	private function devicesListForm() {
		return [
			"type" => "List",
		        "name" => "devicesList",
			"add" => true,
			"caption" => "Geräte",
			"columns" => [
				[
					"add" => 0,
					"caption" => "ID",
					"edit" => [
						"type" => "SelectInstance",
						"validModules" => ["{5FC7B1D7-ED60-B72C-EA50-A8135F4E387A}"]
					],
					"name" => "deviceID",
					"quickFilter" => true,
					"save" => true,
					"width" => "auto"
				]
			],
			"delete" => true,
			"rowCount" => 10,
			"values" => $this->devicesAsListValues(json_decode($this->ReadAttributeString("devices"))),
			"loadValuesFromConfiguration" => false
		];
	}
	private function statesListForm() {
		$states = json_decode($this->ReadAttributeString("states"), true);
		$maxIndex = empty($states) ? 0 : max(array_keys($states));
		$allDevicesEmptyList = array_map(function ($x) {return ["deviceID"=>$x, "devValue"=>""];}, json_decode($this->ReadAttributeString("devices"), true));
		/*
		 * has to be something like:
		 * [
		 * 	[
		 * 		"stateID" => <id>,
		 * 		"stateName" => <name>,
		 * 		"stateValues" => [
		 * 			[
		 * 				"deviceID" => 12345,
		 * 				"devValue" => <string>
		 * 			], ...
		 * 		],
		 * 		"rowColor" => "#FFFFC0"	// iff at least one device has value ""
		 * 	], ....
		 * ]
		 */
		$stateValuesList = function ($state_vals) use($allDevicesEmptyList) {
			$devices_list = $allDevicesEmptyList;	// create a copy
			foreach ($devices_list as &$dev) {
				if (array_key_exists($dev["deviceID"], $state_vals)) {
					$dev["devValue"] = $state_vals[$dev["deviceID"]];
				}
			}
			return $devices_list;
		};
		$statesListMap = function ($id, $val) use($stateValuesList, $allDevicesEmptyList) {
			return [
				"stateID" => $id,
				"stateName" => $val["name"],
				"stateValues" => $stateValuesList($val["values"]),
				"rowColor" => (in_array("", $val["values"]) || count($allDevicesEmptyList) != count($val["values"]) ? "#FFFFC0" : "transparent")
			];
		};
		$stateValues = array_map($statesListMap, array_keys($states), $states);
		return [
			"type" => "List",
		        "name" => "statesList",
			"add" => true,
			"caption" => "Zustände",
			"columns" => [
				[
					"add" => $maxIndex + 1,	// TODO auto increment when adding an element
					"caption" => "ID",
					"edit" => [
						"type" => "NumberSpinner"
					],
					"name" => "stateID",
					"quickFilter" => true,
					"save" => true,
					"width" => "100px"
				],
				[
					"add" => "NeuerZustand",
					"caption" => "Zustand",
					"edit" => [
						"type" => "ValidationTextBox",
						"validate" => "[a-zA-Z0-9]*"
					],
					"name" => "stateName",
					"quickFilter" => true,
					"save" => true,
					"width" => "auto"
				],
				[
					"add" => $allDevicesEmptyList,
					"caption" => "Werte",
					"edit" => [
						"type" => "List",
						"add" => false,
						"columns" => [
							[
								"caption" => "Gerät",
								"name" => "deviceID",
								"edit" => [
									"type" => "SelectInstance",
									"enabled" => false
								],
								"quickFilter" => true,
								"save" => true,
								"width" => "auto"
							],
							[
								"caption" => "Wert",
								"name" => "devValue",
								"edit" => [
									"type" => "ValidationTextBox"
								],
								"quickFilter" => false,
								"save" => true,
								"width" => "300px"
							]
						],
						"delete" => false,
						//"values" => $allDevicesEmptyList,
						"loadValuesFromConfiguration" => true
					],
					"name" => "stateValues",
					"quickFilter" => false,
					"save" => true,
					"width" => "0px",
					"visible" => false
				]
			],
			"delete" => true,
			"rowCount" => 10,
			"values" => $stateValues,
			"loadValuesFromConfiguration" => false,
			"onAdd" => "StateM_UpdateNextStateListIndex(\$id, \$statesList);",
			"onChangeOrder" => "StateM_UpdateNextStateListIndex(\$id, \$statesList);",
			"onDelete" => "StateM_UpdateNextStateListIndex(\$id, \$statesList);",
			"onEdit" => "StateM_UpdateNextStateListIndex(\$id, \$statesList);"
		];
	}
	private function stategroupsListForm() {
		return ["type" => "Label", "caption" => "DUMMY"];
	}
	private function triggerListFrom() {
		return ["type" => "Label", "caption" => "DUMMY"];
	}
	private function transitionsListForm() {
		return ["type" => "Label", "caption" => "DUMMY"];
	}

	private function devicesAsListValues($deviceList) {
		return array_map(function ($x) {return ["deviceID" => $x];}, $deviceList);
	}
}

