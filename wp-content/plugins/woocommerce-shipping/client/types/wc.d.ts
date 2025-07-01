export type WC = Record< string, unknown > & {
	wcSettings: Record< string, unknown > & {
		WC_VERSION: string;
	};
};
