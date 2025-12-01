<?

class Statemachine extends IPSModule {
	// Overrides the internal IPS_Create($id) function
	public function Create(): void {
		// Don't delete this line
		parent::Create();

		// use HTML Visualisation to display state Transitions
		$this->SetVisualizationType(1);
		
		// variables
		$this->RegisterVariableString("state", "Zustand");
		$this->RegisterVariableInteger("stateID", "ZustandsID");
		$this->EnableAction("state");
		$this->EnableAction("stateID");

		// use atributes, so that we can alter and format them as we want
		$this->RegisterAttributeString("devices", "[]");	// json encoded list of devices that are part of this Statemachine
		$this->RegisterAttributeString("states", "[]");		// json encoded list of states each is [<genid> => ["id" => int, "name"=> string, "values" => [<instanceID> => Value]]]
		$this->RegisterAttributeString("stateGroups", "[]");		// json encoded list of stateGroups each is [<genid> => ["name"=> string, "states" => [<stateGenID>]]]
		$this->RegisterAttributeString("triggers", "[]");	// stored as found in the triggers list
		$this->RegisterAttributeString("transitions", "[]");	// stored as found in the transitions list

		$this->RegisterAttributeString("activeState", "");	// GenID of the active State. IF not a valide state set it to the First STate in the list on applyChanges

		// we need the prperty or the apply button will never show up if the list has a name :(
		$this->RegisterPropertyString("devicesList", "[]");
		$this->RegisterPropertyString("statesList", "[]");
		$this->RegisterPropertyString("stateGroupsList", "[]");
		$this->RegisterPropertyString("triggersList", "[]");
		$this->RegisterPropertyString("transitionsList", "[]");
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

		
		// remove old messages and references
		foreach ($this->GetMessageList() as $senderID => $messages) {
			foreach ($messages as $message) {
				$this->UnregisterMessage($senderID, $message);
			}
		}
		foreach ($this->GetReferenceList() as $reference) {
			$this->UnregisterReference($reference);
		}

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
		// if not set to a valide value set active State to the first state
		$activeState = $this->ReadAttributeString("activeState");
		if (!empty($convertedStates) && !array_key_exists($activeState, $convertedStates)) {
			$activeState = array_keys($convertedStates)[0];
			$this->WriteAttributeString("activeState", $activeState);
			// update the variables
			$this->SetValue("state", $convertedStates[$activeState]["name"]);
			$this->SetValue("stateID", $convertedStates[$activeState]["id"]);
		}

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
		$triggers = $this->ReadPropertyString("triggersList");
		$this->WriteAttributeString("triggers", $triggers);

		// set up messages for all triggers, that listen to a variable
		$added_ids = [];
		foreach (json_decode($triggers, true) as $trigger) {
			foreach ($trigger["instanceTriggers"] as $inst) {
				$id = $inst["variable"];
				if (!in_array($id, $added_ids)) {
					if (IPS_VariableExists($id)) {
						$this->RegisterMessage($id, VM_UPDATE);
						$this->RegisterReference($id);
					}
					array_push($added_ids, $id);
				}
			}
		}

		// transitions List
		$this->WriteAttributeString("transitions", $this->ReadPropertyString("transitionsList"));

		// update the buffers
		$this->SetupBuffers();
	}

	public function RequestAction ($Ident, $Value) : void {
		$states = json_decode($this->ReadAttributeString("states"), true);
		if ($Ident === "state") {
			// activate the state with name $Value
			foreach ($states as $genID => $state) {
				if ($state["name"] == $Value) {
					$this->ActivateState($genID);
					return;
				}
			}
			echo "Zustand " . $Value . " nicht gefunden";
		} elseif ($Ident === "stateID") {
			// activate the state with user assigned id $Value
			foreach ($states as $genID => $state) {
				if ($state["id"] == $Value) {
					$this->ActivateState($genID);
					return;
				}
			}
			echo "Zustand mit der id " . $Value . " nicht gefunden.";
		}
	}

	public function MessageSink ($TimeStamp, $SenderID, $MessageID, $Data) : void {
		//$this->LogMessage($TimeStamp . $SenderID . $MessageID . print_r($Data, true), 10204);
		// for variable update: $Data is array with 6 elements [0]: new value; [1]: has the values changed; [2]: old value; [3-5] time stamps
		if ($MessageID == VM_UPDATE) {
			$buff_val = $this->GetBufferSave("inst-".$SenderID);
			if ($buff_val == "") {
				$this->LogMessage("No action in MessageSing on VM_UPDATE for " . $SenderID, 10204);
				return;
			}
			$buff_val = json_decode($buff_val, true);
			$activeState = $this->ReadAttributeString("activeState");
			if ($Data[1]) {
				// change (which is also an update)
				$merge = array_merge($buff_val["onUpdate"], $buff_val["onChange"]);
				foreach ($merge as $trigger_name) {
					$this->intTrigger($trigger_name, $activeState);
				}
			} else {
				// on update (without change)
				foreach ($buff_val["onUpdate"] as $trigger_name) {
					$this->intTrigger($trigger_name, $activeState);
				}
			}
		}
	}


	public function Trigger (string $TriggerName) : void {
		$activeState = $this->ReadAttributeString("activeState");
		$this->intTrigger($TriggerName, $activeState);
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
	 * private functions
	 */
	private function intTrigger($trigger, $activeState) {
		// run the trigger Script
		$scriptRes = IPS_RunScriptTextWait($this->GetBufferSave("triggerScript-" . $trigger));
		$res = strtolower(trim($scriptRes));
		if ($res == "true") {
			// continue execution
		} elseif ($res == "false") {
			return;
		} else {
			$message = "TriggerScript returned unecpected response, expected true/false but got: " . $scriptRes;
			$this->LogMessage($mesage, 10205);
			echo $message;
			return;
		}
		// find new State
		$outgoing = $this->GetBufferSave($activeState);
		if ($outgoing == "") {
			// state has no outgoing transitions
			return;
		}
		$outgoing = json_decode($outgoing, true);
		if (array_key_exists($trigger, $outgoing)) {
			$this->ActivateState($outgoing[$trigger]);
		}
	}

	private function ActivateState($stateGenID) {
		$this->WriteAttributeString("activeState", $stateGenID);
		$states = json_decode($this->ReadAttributeString("states"), true);
		// update the variables
		$this->SetValue("state", $states[$stateGenID]["name"]);
		$this->SetValue("stateID", $states[$stateGenID]["id"]);
		$vals = $states[$stateGenID]["values"];
		foreach ($vals as $instID => $value) {
			VirtDev_WriteValue($instID, $value);	// so far only support VirtDev devices
		}

	}

	// return buffer name, and set up the buffers, if they are not
	private function GetBufferSave($name) {
		$res = $this->GetBuffer($name);
		if ($res == "") {
			$this->SetupBuffers();
			$res = $this->GetBuffer($name);
		}
		return $res;
	}
	private function SetupBuffers() {
		// inst-<ipsID> Buffers and triggerScript-<ScriptName> and build the trigger Name table
		$triggerNameTable = [];
		$data = [];
		foreach (json_decode($this->ReadAttributeString("triggers"), true) as $trigger) {
			foreach ($trigger["instanceTriggers"] as $inst) {
				$key = "inst-" . $inst["variable"];
				if (!array_key_exists($key, $data)) {
					$data[$key] = ["onUpdate" => [], "onChange" => []];
				}
				if ($inst["variableTriggerType"] == 0) {
					array_push($data[$key]["onUpdate"], $trigger["triggerName"]);
				} else {
					array_push($data[$key]["onChange"], $trigger["triggerName"]);
				}
			}
			$this->SetBuffer("triggerScript-" . $trigger["triggerName"], $trigger["triggerScript"]);
			$triggerNameTable[$trigger["triggerGenID"]] = $trigger["triggerName"];
		}
		foreach ($data as $key => $value) {
			$this->SetBuffer($key, json_encode($value));
		}

		// <stateGenID> Buffers; each array for trigger name to newState
		$data = [];
		foreach (json_decode($this->ReadAttributeString("transitions"), true) as $transition) {
			$start = $transition["transitionStart"];
			$trigger = $triggerNameTable[$transition["transitionTrigger"]];
			if (str_starts_with($start, "state-")) {
				if (!array_key_exists($start, $data)) {
					$data[$start] = ["fromState" => [], "fromGroup" => []];		// to be able to detect double assignments
				}
				if (!array_key_exists($trigger, $data[$start]["fromState"])) {
					$data[$start]["fromState"][$trigger] = $transition["transitionEnd"];
				} else {
					$problemState = json_decode($this->ReadAttributeString("states"))[$start];
					$this->LogMessage("Zustandsautomat hat mindestens zwei Zustandsübergänge mit dem selben Trigger aus Zustand " . $problemState, 10205);
				}
			} else {
				// iterate through all states in the state Group
				$stateList = json_decode($this->ReadAttributeString("stateGroups"), true)[$start]["states"];
				foreach ($stateList as $state) {
					if (!array_key_exists($state, $data)) {
						$data[$state] = ["fromState" => [], "fromGroup" => []];		// to be able to detect double assignments
					}
					if (!array_key_exists($trigger, $data[$state]["fromGroup"])) {
						$data[$state]["fromGroup"][$trigger] = $transition["transitionEnd"];
					} else {
						$problemState = json_decode($this->ReadAttributeString("states"))[$state];
						$this->LogMessage("Zustandsautomat hat mindestens zwei Zustandsübergänge mit dem selben Trigger aus Zustand " . $problemState . " durch eine oder mehrere Zustandsgruppen", 10205);
					}
				}
			}
		}
		foreach ($data as $key => $value) {
			$combinedValue = array_merge($value["fromGroup"], $value["fromState"]);	// fromState takes precedence for same trigger
			$this->SetBuffer($key, json_encode($combinedValue));
		}
	}


	/*
	 * HTML-SDK Functions
	 */
	public function GetVisualizationTile() {
		$vals = "<script> let states = " . $this->ReadAttributeString("states") . ";
				let stateGroups = " . $this->ReadAttributeString("stateGroups") . ";
				let transitions = " . $this->ReadAttributeString("transitions") . ";
				let activeState = \"" . $this->ReadAttributeString("activeState") . "\";
			</script>";
		return $vals + file_get_contents(__DIR__ . '/module.html');
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
					"add" => uniqid("stateGroup-"),
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
						"type" => "ScriptEditor"
					],
					"name" => "triggerScript",
					"quickFilter" => false,
					"save" => true,
					"width" => "500px"
				],
				[
					"add" => uniqid("trigger-"),
					"caption" => "internal ID",
					"name" => "triggerGenID",
					"quickFilter" => false,
					"save" => true,
					"width" => "0px",
					"visible" => false
				]
			],
			"delete" => true,
			"rowCount" => 10,
			"values" => $triggerValues,
			"loadValuesFromConfiguration" => false
		];
	}
	private function transitionsListForm() {
		$transitions = json_decode($this->ReadAttributeString("transitions"), true);
		$states = json_decode($this->ReadAttributeString("states"), true);
		$stateOptions = array_map(function ($genID, $val) {return ["value" => $genID, "caption" => ($val["id"] . " - " . $val["name"])];}, array_keys($states), $states);
		$stateGroups = json_decode($this->ReadAttributeString("stateGroups"), true);
		$stateGroupOptions = array_map(function ($genID, $val) {return ["value" => $genID, "caption" => ("Gruppe - " . $val["name"])];}, array_keys($stateGroups), $stateGroups);
		$stateAndStateGroupOptions = array_merge($stateOptions, $stateGroupOptions);
		$triggers = json_decode($this->ReadAttributeString("triggers"), true);
		$triggerOptions = array_map(function($val) {return ["value" => $val["triggerGenID"], "caption" => $val["triggerName"]];}, $triggers);
		return [
			"type" => "List",
		        "name" => "transitionsList",
			"add" => true,
			"caption" => "Zuständsübergänge",
			"columns" => [
				[
					"add" => "",
					"caption" => "Startzustand",
					"edit" => [
						"type" => "Select",
						"options" => $stateAndStateGroupOptions
					],
					"name" => "transitionStart",
					"quickFilter" => true,
					"save" => true,
					"width" => "300px"
				],
				[
					"add" => "",
					"caption" => "Zielzustand",
					"edit" => [
						"type" => "Select",
						"options" => $stateOptions
					],
					"name" => "transitionEnd",
					"quickFilter" => true,
					"save" => true,
					"width" => "300px"
				],
				[
					"add" => "",
					"caption" => "Auslöser",
					"edit" => [
						"type" => "Select",
						"options" => $triggerOptions
					],
					"name" => "transitionTrigger",
					"quickFilter" => true,
					"save" => true,
					"width" => "auto"
				]
			],
			"delete" => true,
			"rowCount" => 10,
			"values" => $transitions,
			"loadValuesFromConfiguration" => false
		];
	}

	private function devicesAsListValues($deviceList) {
		return array_map(function ($x) {return ["deviceID" => $x];}, $deviceList);
	}
}

