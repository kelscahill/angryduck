export interface UPSDAPStrategyResponse {
	origin_address: Record<
		string,
		{
			has_agreed_to_tos: boolean;
		}
	>;
}

export type UPSDAPStrategy = camelCase< UPSDAPStrategyResponse >;
