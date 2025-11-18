<?

class Statemachine extends IPSModule {
	// Overrides the internal IPS_Create($id) function
	public function Create(): void {
		// Don't delete this line
		parent::Create();
		
		// use atributes, so that we can alter and format them as we want
		$this->RegisterAttributeString("devices", "[]");	// json encoded list of devices that are part of this Statemachine
	}

	// dynamic configurationform
	public function GetConfigurationForm () : string {
		$res = ["elements" => [
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
	}

	public function RequestAction ($Ident, $Value) : void {
	}

	public function AddDevices($parentID) : void {
		// add all VirtDev devices that are in the category $parentID
		$newDevices = array_filter(IPS_GetChildrenIDs($parentID), function($x) {return IPS_GetObject($x)["ObjectType"] == 1 && IPS_GetInstance($x)["ModuleInfo"]["ModuleID"] == "{5FC7B1D7-ED60-B72C-EA50-A8135F4E387A}";});
		$devices = array_unique(array_merge(json_decode($this->ReadAttributeString("devices")), $newDevices));
		// update devices list in the settings
		//$this->UpdateFormField("devicesList", "values", json_encode($this->devicesAsListValues($devices)));
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
		        //"name" => "devicesList",
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
					"save" => false,
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
		return ["type" => "Label", "caption" => "DUMMY"];
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

