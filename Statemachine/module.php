<?

class VirtualDevice extends IPSModule {
	// Overrides the internal IPS_Create($id) function
	public function Create(): void {
		// Don't delete this line
		parent::Create();

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
		return json_encode($res);
	}

	// Overwrites the internal IPS_ApplyChanges($id) function
	public function ApplyChanges(): void {
	}

	public function RequestAction ($Ident, $Value) : void {
	}

	/* 
	 * private Form functions
	 */
	private function addDevicesButtonForm() {
		return [
			"type" => "List",
			"caption" => "Alle Geräte einer Kategorie hinzufügen",
			"popup" => [
				"buttons" => [
					"caption" => "Hinzufügen",
					"onClick" => [
						'echo "TODO hinzufügen implementieren, Kategorie ist $CategoryToAdd";'
					]
				]
				"caption" => "Alle Geräte einer Kategorie zum Zustandsautomaten hinzufügen",
				"items" => [
					"type" => "SelectCategory",
					"caption" => "Kategorie",
					"name" => "CategoryToAdd"
				]
			]
		];
	}
}

