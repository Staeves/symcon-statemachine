<?

class VirtualDevice extends IPSModule {
	// Overrides the internal IPS_Create($id) function
	public function Create(): void {
		// Don't delete this line
		parent::Create();

	}

	// dynamic configurationform
	public function GetConfigurationForm () : string {
		return "";
	}

	// Overwrites the internal IPS_ApplyChanges($id) function
	public function ApplyChanges(): void {
	}

	public function RequestAction ($Ident, $Value) : void {
	}
}

