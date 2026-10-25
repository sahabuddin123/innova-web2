import { get, post, put, del, API_ENDPOINTS } from '../api';
import {
  CertificateIcbd,
  CertificateBteb,
  CertificateEligibility,
  PublicVerificationResult,
  CertificateSummaryReport,
  IcbdFormData,
  BtebFormData,
  CertificateFilters,
  CertificateSessionBrief,
  ApiMeta,
} from '../types';

/**
 * Service for Institutional & BTEB Certificates, Eligibility Checks,
 * QR Generation, and Public Verification.
 */
export const certificateService = {
  // =========================================================================
  // Institutional (ICBD) Certificates
  // =========================================================================

  /**
   * Retrieve paginated ICBD certificates with filters.
   */
  async getIcbdCertificates(
    filters?: CertificateFilters
  ): Promise<{ data: CertificateIcbd[]; meta?: ApiMeta }> {
    const params: Record<string, unknown> = {};

    if (filters) {
      if (filters.search) params.search = filters.search;
      if (filters.course_id) params.course_id = filters.course_id;
      if (filters.batch_id) params.batch_id = filters.batch_id;
      if (filters.is_active !== undefined && filters.is_active !== '') {
        params.is_active = filters.is_active;
      }
      if (filters.result) params.result = filters.result;
      if (filters.start_date) params.start_date = filters.start_date;
      if (filters.end_date) params.end_date = filters.end_date;
      if (filters.page) params.page = filters.page;
      if (filters.per_page) params.per_page = filters.per_page;
    }

    const response = await get<CertificateIcbd[]>(API_ENDPOINTS.certificates.icbd, { params });
    return {
      data: response.data,
      meta: response.meta,
    };
  },

  /**
   * Retrieve single ICBD certificate by ID.
   */
  async getIcbdCertificate(id: number | string): Promise<CertificateIcbd> {
    const response = await get<CertificateIcbd>(`${API_ENDPOINTS.certificates.icbd}/${id}`);
    return response.data;
  },

  /**
   * Issue a new ICBD certificate for an eligible student.
   */
  async issueIcbdCertificate(data: IcbdFormData): Promise<CertificateIcbd> {
    const response = await post<CertificateIcbd>(API_ENDPOINTS.certificates.icbd, data);
    return response.data;
  },

  /**
   * Update an existing ICBD certificate record.
   */
  async updateIcbdCertificate(
    id: number | string,
    data: Partial<IcbdFormData>
  ): Promise<CertificateIcbd> {
    const response = await put<CertificateIcbd>(`${API_ENDPOINTS.certificates.icbd}/${id}`, data);
    return response.data;
  },

  /**
   * Transition ICBD certificate lifecycle status.
   */
  async updateIcbdStatus(id: number | string, isActive: number): Promise<CertificateIcbd> {
    const response = await post<CertificateIcbd>(
      `${API_ENDPOINTS.certificates.icbd}/${id}/status`,
      { is_active: isActive }
    );
    return response.data;
  },

  /**
   * Delete or revoke an ICBD certificate.
   */
  async deleteIcbdCertificate(id: number | string): Promise<void> {
    await del(`${API_ENDPOINTS.certificates.icbd}/${id}`);
  },

  /**
   * Get SVG QR Code data URI for verification.
   */
  async getIcbdQr(
    id: number | string
  ): Promise<{ certificate_no: string; verification_url: string; qr_data_uri: string }> {
    const response = await get<{
      certificate_no: string;
      verification_url: string;
      qr_data_uri: string;
    }>(`${API_ENDPOINTS.certificates.icbd}/${id}/qr`);
    return response.data;
  },

  // =========================================================================
  // Technical Board (BTEB) Certificates
  // =========================================================================

  /**
   * Retrieve paginated BTEB certificates with filters.
   */
  async getBtebCertificates(
    filters?: CertificateFilters
  ): Promise<{ data: CertificateBteb[]; meta?: ApiMeta }> {
    const params: Record<string, unknown> = {};

    if (filters) {
      if (filters.search) params.search = filters.search;
      if (filters.session_id) params.session_id = filters.session_id;
      if (filters.is_active !== undefined && filters.is_active !== '') {
        params.IsActive = filters.is_active;
      }
      if (filters.result) params.Result = filters.result;
      if (filters.page) params.page = filters.page;
      if (filters.per_page) params.per_page = filters.per_page;
    }

    const response = await get<CertificateBteb[]>(API_ENDPOINTS.certificates.bteb, { params });
    return {
      data: response.data,
      meta: response.meta,
    };
  },

  /**
   * Retrieve single BTEB certificate by ID.
   */
  async getBtebCertificate(id: number | string): Promise<CertificateBteb> {
    const response = await get<CertificateBteb>(`${API_ENDPOINTS.certificates.bteb}/${id}`);
    return response.data;
  },

  /**
   * Record a new BTEB board certificate.
   */
  async createBtebCertificate(data: BtebFormData): Promise<CertificateBteb> {
    const response = await post<CertificateBteb>(API_ENDPOINTS.certificates.bteb, data);
    return response.data;
  },

  /**
   * Update an existing BTEB board certificate record.
   */
  async updateBtebCertificate(
    id: number | string,
    data: Partial<BtebFormData>
  ): Promise<CertificateBteb> {
    const response = await put<CertificateBteb>(`${API_ENDPOINTS.certificates.bteb}/${id}`, data);
    return response.data;
  },

  /**
   * Soft-delete a BTEB certificate.
   */
  async deleteBtebCertificate(id: number | string): Promise<void> {
    await del(`${API_ENDPOINTS.certificates.bteb}/${id}`);
  },

  // =========================================================================
  // Assessment, Verification & Reporting
  // =========================================================================

  /**
   * Evaluate student eligibility for certificate issuance.
   */
  async checkEligibility(studentId: number | string): Promise<CertificateEligibility> {
    const response = await get<CertificateEligibility>(
      API_ENDPOINTS.certificates.eligibility(studentId)
    );
    return response.data;
  },

  /**
   * Public certificate verification (unauthenticated).
   */
  async verifyPublicCertificate(identifier: string): Promise<PublicVerificationResult> {
    const response = await get<PublicVerificationResult>(
      API_ENDPOINTS.certificates.verify(encodeURIComponent(identifier.trim()))
    );
    return response.data;
  },

  /**
   * Retrieve academic/technical board sessions.
   */
  async getSessions(): Promise<CertificateSessionBrief[]> {
    const response = await get<CertificateSessionBrief[]>(API_ENDPOINTS.academic.sessions);
    return response.data;
  },

  /**
   * Aggregated Certificate Summary Reporting.
   */
  async getSummaryReport(): Promise<CertificateSummaryReport> {
    const response = await get<CertificateSummaryReport>(API_ENDPOINTS.certificates.summary);
    return response.data;
  },
};
