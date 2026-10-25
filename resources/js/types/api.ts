/**
 * INNOVA WEB — API v1 TypeScript Contracts & Envelope Types
 *
 * Establishes typed client contracts matching the backend ApiController v1 envelopes.
 */

/**
 * Standard API v1 Success Response Envelope
 */
export interface ApiResponse<T> {
  success: true;
  message: string;
  data: T;
  meta?: ApiMeta;
}

/**
 * Standard API v1 Error Response Envelope
 */
export interface ApiErrorResponse {
  success: false;
  message: string;
  errors?: Record<string, string[]> | string | null;
  status?: number;
}

/**
 * Metadata block for API v1 responses (pagination, server timestamps, etc.)
 */
export interface ApiMeta {
  timestamp?: string;
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  [key: string]: unknown;
}

/**
 * Foundation Ping Payload
 */
export interface ApiPingData {
  name: string;
  api_version: string;
  environment: string;
  timestamp: string;
}

/**
 * Type guard to check if an API response is successful
 */
export function isApiSuccess<T>(response: ApiResponse<T> | ApiErrorResponse): response is ApiResponse<T> {
  return response.success === true;
}

/**
 * Type guard to check if an API response is an error
 */
export function isApiError(response: ApiResponse<unknown> | ApiErrorResponse): response is ApiErrorResponse {
  return response.success === false;
}
