import { APIRequestContext, request } from "@playwright/test";

const {
	DINTERO_API_USERNAME,
	DINTERO_API_PASSWORD,
	DINTERO_ACCOUNT_ID,
} = process.env;

export const GetDinteroApiClient = async (): Promise<APIRequestContext> => {
	return await request.newContext({
		baseURL: `https://api.dintero.com/v1/accounts/${DINTERO_ACCOUNT_ID}https://checkout.dintero.com/v1/sessions-profile`,
		extraHTTPHeaders: {
			Authorization: `Basic ${Buffer.from(
				`${DINTERO_API_USERNAME ?? 'admin'}:${DINTERO_API_PASSWORD ?? 'password'}`
			).toString('base64')}`,
		},
	});
}

export const SetDinteroSettings = async (wcApiClient: APIRequestContext) => {
	// Set api credentials and enable the gateway.
	if (DINTERO_API_USERNAME) {
		const settings = {
			enabled: true,
			settings: {
				test_mode: "yes",
				logging: "yes",
				client_id: DINTERO_API_USERNAME,
				client_secret: DINTERO_API_PASSWORD,
				account_id: DINTERO_ACCOUNT_ID,
				form_factor: "redirect",
			}
		};

		// Update settings.
		await wcApiClient.post('payment_gateways/dintero_checkout', { data: settings });
	}
}
