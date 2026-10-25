import { get, post, put, API_ENDPOINTS } from '../api';
import {
  FeeTransaction,
  FeeFinancialSummary,
  StudentFeeBalanceResponse,
  FeeReceiptData,
  StoreFeeTransactionPayload,
  UpdateFeeTransactionPayload,
  FeeFilters,
  BkashQueryResult,
  ApiMeta,
  Student,
} from '../types';

interface LaravelPaginatedData<T> {
  current_page?: number;
  data: T[];
  total?: number;
  per_page?: number;
  last_page?: number;
}

export const feeService = {
  /**
   * Retrieve paginated fee transactions with filters.
   */
  async getTransactions(
    filters?: FeeFilters
  ): Promise<{ data: FeeTransaction[]; meta: ApiMeta }> {
    const params: Record<string, unknown> = {};

    if (filters) {
      if (filters.student_id) params.student_id = filters.student_id;
      if (filters.payment_type !== undefined && filters.payment_type !== '') {
        params.payment_type = filters.payment_type;
      }
      if (filters.channel !== undefined && filters.channel !== null) {
        params.channel = filters.channel;
      }
      if (filters.status !== undefined && filters.status !== null) {
        params.status = filters.status;
      }
      if (filters.start_date) params.start_date = filters.start_date;
      if (filters.end_date) params.end_date = filters.end_date;
      if (filters.trx_id) params.trx_id = filters.trx_id;
      if (filters.page) params.page = filters.page;
      if (filters.per_page) params.per_page = filters.per_page;
    }

    const response = await get<LaravelPaginatedData<FeeTransaction> | FeeTransaction[]>(
      API_ENDPOINTS.fees.transactions,
      { params }
    );

    // Backend ApiController returns $transactions directly in data
    // which for a LengthAwarePaginator contains a nested .data array
    const rawData = response.data;
    if (rawData && typeof rawData === 'object' && 'data' in rawData && Array.isArray((rawData as LaravelPaginatedData<FeeTransaction>).data)) {
      const paginated = rawData as LaravelPaginatedData<FeeTransaction>;
      return {
        data: paginated.data,
        meta: {
          current_page: paginated.current_page ?? 1,
          last_page: paginated.last_page ?? 1,
          per_page: paginated.per_page ?? 15,
          total: paginated.total ?? paginated.data.length,
        },
      };
    }

    return {
      data: Array.isArray(rawData) ? rawData : [],
      meta: response.meta || {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: Array.isArray(rawData) ? rawData.length : 0,
      },
    };
  },

  /**
   * Retrieve single fee transaction by ID.
   */
  async getTransaction(id: number | string): Promise<FeeTransaction> {
    const response = await get<FeeTransaction>(API_ENDPOINTS.fees.transaction(id));
    return response.data;
  },

  /**
   * Record a new manual or counter fee transaction.
   */
  async createTransaction(
    payload: StoreFeeTransactionPayload
  ): Promise<{ transaction: FeeTransaction; financial_summary: FeeFinancialSummary }> {
    const response = await post<{
      transaction: FeeTransaction;
      financial_summary: FeeFinancialSummary;
    }>(API_ENDPOINTS.fees.transactions, payload);
    return response.data;
  },

  /**
   * Update an existing fee transaction.
   */
  async updateTransaction(
    id: number | string,
    payload: UpdateFeeTransactionPayload
  ): Promise<FeeTransaction> {
    const response = await put<FeeTransaction>(API_ENDPOINTS.fees.transaction(id), payload);
    return response.data;
  },

  /**
   * Update fee transaction status (0 = Approved, 1 = Pending).
   */
  async updateStatus(
    id: number | string,
    status: number
  ): Promise<{ transaction: FeeTransaction; financial_summary?: FeeFinancialSummary | null }> {
    const response = await put<{
      transaction: FeeTransaction;
      financial_summary: FeeFinancialSummary | null;
    }>(API_ENDPOINTS.fees.status(id), { status });
    return response.data;
  },

  /**
   * Retrieve printable receipt data.
   */
  async getReceipt(id: number | string): Promise<FeeReceiptData> {
    const response = await get<FeeReceiptData>(API_ENDPOINTS.fees.receipt(id));
    return response.data;
  },

  /**
   * Retrieve student balance and complete transaction ledger.
   */
  async getStudentBalance(studentId: number | string): Promise<StudentFeeBalanceResponse> {
    const response = await get<StudentFeeBalanceResponse>(
      API_ENDPOINTS.fees.studentBalance(studentId)
    );
    return response.data;
  },

  /**
   * Query bKash payment gateway status by payment ID.
   */
  async queryBkashPayment(paymentId: string): Promise<BkashQueryResult> {
    const response = await get<BkashQueryResult>(API_ENDPOINTS.fees.bkashQuery(paymentId));
    return response.data;
  },

  /**
   * Search bKash transaction status by transaction ID.
   */
  async searchBkashTrx(trxId: string): Promise<BkashQueryResult> {
    const response = await get<BkashQueryResult>(API_ENDPOINTS.fees.bkashSearch(trxId));
    return response.data;
  },

  /**
   * Search students catalog for auto-fill in payment collection.
   */
  async searchStudents(search: string): Promise<Student[]> {
    if (!search || search.trim().length === 0) return [];
    const response = await get<Student[]>(API_ENDPOINTS.academic.students, {
      params: { search: search.trim(), per_page: 20 },
    });
    return response.data || [];
  },
};
