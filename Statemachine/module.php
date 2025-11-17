<?

class Statemachine extends IPSModule {
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
		$json = json_encode($res);
		/*echo $json;*/
		return '{
    "elements":
    [
        { "type": "ValidationTextBox", "name": "Username", "caption": "Username" },
        { "type": "ValidationTextBox", "name": "Password", "caption": "Password" },
        { "type": "NumberSpinner", "name": "APIID", "caption": "APIID" },
        { "type": "ValidationTextBox", "name": "Sender", "caption": "Sender" },
        { "type": "Select", "name": "SMSType", "caption": "Type",
            "options": [
                { "label": "SMS", "value": 0 },
                { "label": "Combi-SMS", "value": 1 },
                { "label": "MMS", "value": 2 }
        ]}
    ],
    "actions":
    [
        { "type": "ValidationTextBox", "name": "Number", "caption": "Number" },
        { "type": "ValidationTextBox", "name": "Message", "caption": "Message" },
        { "type": "Button", "label": "Send Message", "onClick": "SMS_Send($id, $Number, $Message);" }
    ],
    "status":
    [
        { "code": 102, "icon": "active", "caption": "' . $json . '" },
        { "code": 201, "icon": "error", "caption": "Authentication failed" },
        { "code": 202, "icon": "error", "caption": "No credits left" }
    ]
}';
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
				],
				"caption" => "Alle Geräte einer Kategorie zum Zustandsautomaten hinzufügen",
				"items" => [
					"type" => "SelectCategory",
					"caption" => "Kategorie",
					"name" => "CategoryToAdd"
				]
			]
		];
	}
	private function devicesListForm() {
		return ["type" => "Label", "caption" => "DUMMY"];
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
}

