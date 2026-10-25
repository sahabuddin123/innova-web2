import { useState, useCallback } from 'react';
import { ApiResponse, ApiErrorResponse } from '../types/api';

export interface UseApiState<T> {
  data: T | null;
  loading: boolean;
  error: ApiErrorResponse | null;
}

/**
 * Custom hook to execute asynchronous API functions with typed loading and error state management.
 */
export function useApi<T, A extends unknown[]>(
  apiFn: (...args: A) => Promise<ApiResponse<T>>
) {
  const [state, setState] = useState<UseApiState<T>>({
    data: null,
    loading: false,
    error: null,
  });

  const execute = useCallback(
    async (...args: A): Promise<T | null> => {
      setState((prev) => ({ ...prev, loading: true, error: null }));
      try {
        const response = await apiFn(...args);
        setState({ data: response.data, loading: false, error: null });
        return response.data;
      } catch (err) {
        const errorResponse = err as ApiErrorResponse;
        setState({ data: null, loading: false, error: errorResponse });
        return null;
      }
    },
    [apiFn]
  );

  const reset = useCallback(() => {
    setState({ data: null, loading: false, error: null });
  }, []);

  return {
    ...state,
    execute,
    reset,
  };
}
