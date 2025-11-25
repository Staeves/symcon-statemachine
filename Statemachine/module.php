<?

class Statemachine extends IPSModule {
	// Overrides the internal IPS_Create($id) function
	public function Create(): void {
		// Don't delete this line
		parent::Create();
		
		// use atributes, so that we can alter and format them as we want
		$this->RegisterAttributeString("devices", "[]");	// json encoded list of devices that are part of this Statemachine
		$this->RegisterAttributeString("states", "[]");		// json encoded list of states each is [<genid> => ["id" => int, "name"=> string, "values" => [<instanceID> => Value]]]
		$this->RegisterAttributeString("stateGroups", "[]");		// json encoded list of stateGroups each is [<genid> => ["name"=> string, "states" => [<stateGenID>]]]
		$this->RegisterAttributeString("triggers", "[]");	// stored as found in the triggers list

		// we need the prperty or the apply button will never show up if the list has a name :(
		$this->RegisterPropertyString("devicesList", "[]");
		$this->RegisterPropertyString("statesList", "[]");
		$this->RegisterPropertyString("stateGroupsList", "[]");
		$this->RegisterPropertyString("triggersList", "[]");
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
			return ["id" => $x["stateID"], "name" => $x["stateName"], "values" => $valuesArray];
		};
		$getKeys = function ($x) {
			return $x["stateGenID"];
		};
		$convertedStates = array_combine(array_map($getKeys, $stateData), array_map($convertStates, $stateData));
		$this->WriteAttributeString("states", json_encode($convertedStates));

		// state group List
		$stateGroupData = json_decode($this->ReadPropertyString("stateGroupsList"), true);
		$convertStateGroups = function($x) use ($convertedStates) {
			$filteredStateGroupStates = array_filter($x["stateGroupStates"], function ($y) use ($convertedStates) {return in_array($y["stateGenID"], array_keys($convertedStates));});
			$statesArray = array_map(function ($y) {return $y["stateGenID"];}, $filteredStateGroupStates);
			return ["name" => $x["stateGroupName"], "states" => $statesArray];
		};
		$getKeys = function ($x) {
			return $x["stateGroupGenID"];
		};
		$convertedStateGroups = array_combine(array_map($getKeys, $stateGroupData), array_map($convertStateGroups, $stateGroupData));
		$this->WriteAttributeString("stateGroups", json_encode($convertedStateGroups));

		// triggers List
		$this->WriteAttributeString("triggers", $this->ReadPropertyString("triggersList"));

		// Reload the form to make shure it is updated
		$this->ReloadForm();
	}

	public function RequestAction ($Ident, $Value) : void {
	}

	public function AddDevices(int $parentID) : void {
		// add all VirtDev devices that are in the category $parentID
		$newDevices = array_filter(IPS_GetChildrenIDs($parentID), function($x) {return IPS_GetObject($x)["ObjectType"] == 1 && IPS_GetInstance($x)["ModuleInfo"]["ModuleID"] == "{5FC7B1D7-ED60-B72C-EA50-A8135F4E387A}";});
		$devices = array_values(array_unique(array_merge(json_decode($this->ReadAttributeString("devices")), $newDevices)));	// array_values makes keys numeric again
		// update devices list in the settings
		$this->UpdateFormField("devicesList", "values", json_encode($this->devicesAsListValues($devices)));
	}
	public function UpdateNextStateListIndex(mixed $states) : void {
		$maxIndex = empty($states) ? 0 : max(array_map(function ($x) {return $x["stateID"];}, iterator_to_array($states)));
		$this->UpdateFormField("statesList", "columns.0.add", $maxIndex+1);
		$this->UpdateFormField("statesList", "columns.3.add", uniqid("state-"));
	}
	public function UpdateNextStateGroupListIndex() : void {
		$this->UpdateFormField("stateGroupsList", "columns.2.add", uniqid("group-"));
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
		$maxIndex = empty($states) ? 0 : max(array_map(function ($x) {return $x["id"];}, $states));
		$allDevicesEmptyList = array_map(function ($x) {return ["deviceID"=>$x, "devValue"=>""];}, json_decode($this->ReadAttributeString("devices"), true));
		/*
		 * has to be something like:
		 * [
		 * 	[
		 * 		"stateID" => <id>,
		 * 		"stateGenID" => state-<uniqueid()>,	
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
		$statesListMap = function ($genID, $val) use($stateValuesList, $allDevicesEmptyList) {
			return [
				"stateID" => $val["id"],
				"stateName" => $val["name"],
				"stateGenID" => $genID,
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
					"add" => $maxIndex + 1,
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
						"loadValuesFromConfiguration" => true	// respect the values from the "outer" list
					],
					"name" => "stateValues",
					"quickFilter" => false,
					"save" => true,
					"width" => "0px",
					"visible" => false
				], 
				[
					"add" => uniqid("state-"),
					"caption" => "internal ID",
					"name" => "stateGenID",
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
		$stateGroups = json_decode($this->ReadAttributeString("stateGroups"), true);
		/*
		 * has to be something like:
		 * [
		 * 	[
		 * 		"stateGroupGenID" => group-<id>,
		 * 		"stateGroupName" => <name>,
		 * 		"stateGroupStates" => [
		 * 			[
		 * 				"stateGenID" => xyz
		 * 			], ...
		 * 		]
		 * 	], ....
		 * ]
		 */
		$stateGroupStatesList = function ($stateGroupStates) {
			return array_map(function ($x) {return ["stateGenID" => $x];}, $stateGroupStates);
		};
		$stateGroupsListMap = function ($genID, $val) use($stateGroupStatesList) {
			return [
				"stateGroupGenID" => $genID,
				"stateGroupName" => $val["name"],
				"stateGroupStates" => $stateGroupStatesList($val["states"]),
			];
		};
		$stateGroupValues = array_map($stateGroupsListMap, array_keys($stateGroups), $stateGroups);
		$states = json_decode($this->ReadAttributeString("states"), true);
		$stateOptions = array_map(function ($genID, $val) {return ["value" => $genID, "caption" => ($val["id"] . " - " . $val["name"])];}, array_keys($states), $states);
		return [
			"type" => "List",
		        "name" => "stateGroupsList",
			"add" => true,
			"caption" => "Zuständsgruppen",
			"columns" => [
				[
					"add" => "NeueZustandsgruppe",
					"caption" => "Name",
					"edit" => [
						"type" => "ValidationTextBox",
						"validate" => "[a-zA-Z0-9]*"
					],
					"name" => "stateGroupName",
					"quickFilter" => true,
					"save" => true,
					"width" => "auto"
				],
				[
					"add" => [],
					"caption" => "Zustände",
					"edit" => [
						"type" => "List",
						"add" => true,
						"columns" => [
							[
								"add" => "",
								"caption" => "Zustand",
								"name" => "stateGenID",
								"edit" => [
									"type" => "Select",
									"options" => $stateOptions
								],
								"quickFilter" => true,
								"save" => true,
								"width" => "auto"
							]
						],
						"delete" => true,
						"loadValuesFromConfiguration" => true	// respect the values from the "outer" list
					],
					"name" => "stateGroupStates",
					"quickFilter" => false,
					"save" => true,
					"width" => "300px"
				], 
				[
					"add" => uniqid("state-"),
					"caption" => "internal ID",
					"name" => "stateGroupGenID",
					"quickFilter" => false,
					"save" => true,
					"width" => "0px",
					"visible" => false
				]
			],
			"delete" => true,
			"rowCount" => 10,
			"values" => $stateGroupValues,
			"loadValuesFromConfiguration" => false,
			"onAdd" => "StateM_UpdateNextStateGroupListIndex(\$id);",
			"onChangeOrder" => "StateM_UpdateNextStateGroupListIndex(\$id);",
			"onDelete" => "StateM_UpdateNextStateGroupListIndex(\$id);",
			"onEdit" => "StateM_UpdateNextStateGroupListIndex(\$id);"
		];
	}
	private function triggerListFrom() {
		/*
		 * events that trigger triggers are setup from the object tree, and with execute advanced instance function, passing the name of the trigger
		 * each trigger has to have a name
		 * optionally it can have entries in the list with variable ids, that trigger on update or on change
		 * optionally it can have an script, that can function as a condition
		 */
		$triggerValues = json_decode($this->ReadAttributeString("triggers"), true);
		return [
			"type" => "List",
		        "name" => "triggersList",
			"add" => true,
			"caption" => "Auslöser",
			"columns" => [
				[
					"add" => "NeuerAusloesser",
					"caption" => "Name",
					"edit" => [
						"type" => "ValidationTextBox",
						"validate" => "[a-zA-Z0-9]*"
					],
					"name" => "triggerName",
					"quickFilter" => true,
					"save" => true,
					"width" => "auto"
				],
				[
					"add" => [],
					"caption" => "Variablen",
					"edit" => [
						"type" => "List",
						"add" => true,
						"columns" => [
							[
								"add" => 0,
								"caption" => "Variable",
								"name" => "variable",
								"edit" => [
									"type" => "SelectVariable"
								],
								"quickFilter" => true,
								"save" => true,
								"width" => "auto"
							],
							[
								"add" => 0,
								"caption" => "Auslösung",
								"name" => "variableTriggerType",
								"edit" => [
									"type" => "Select",
									"options" => [
										[
											"caption" => "Beim aktualisieren",
											"value" => 0
										],
										[
											"caption" => "Bei neuem Wert",
											"value" => 1
										]
									]
								],
								"quickFilter" => false,
								"save" => true,
								"width" => "300px"
							]
						],
						"delete" => true,
						"loadValuesFromConfiguration" => true	// respect the values from the "outer" list
					],
					"name" => "instanceTriggers",
					"quickFilter" => false,
					"save" => true,
					"width" => "300px"
				],
				[
					"add" => "// crazy script",
					"caption" => "Bedingungen Skript",
					"edit" => [
						"validate" => "[a-zA-Z0-9]*"
					],
					"name" => "triggerScript",
					"quickFilter" => false,
					"save" => true,
					"width" => "500px"
				]
			],
			"delete" => true,
			"rowCount" => 10,
			"values" => $triggerValues,
			"loadValuesFromConfiguration" => false
		];
	}
	private function transitionsListForm() {
		return ["type" => "Label", "caption" => "DUMMY"];
	}

	private function devicesAsListValues($deviceList) {
		return array_map(function ($x) {return ["deviceID" => $x];}, $deviceList);
	}
}

