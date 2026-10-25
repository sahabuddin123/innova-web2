/**
 * INNOVA WEB — Certificate & Verification TypeScript Contracts
 *
 * Types for Institutional (ICBD) certificates, Technical Board (BTEB) certificates,
 * Student Eligibility Assessment, Public Authenticity Verification, and Reporting.
 */

export interface CertificateStudentBrief {
  id: number;
  sname: string;
  phone?: string | null;
  nid?: string | null;
  image?: string | null;
  course_id?: number | null;
  batch_id?: number | null;
  balance?: string | number | null;
}

export interface CertificateCourseBrief {
  id: number;
  name: string;
  shortTitle?: string | null;
  courseduration?: string | null;
  coursefee?: string | number | null;
}

export interface CertificateBatchBrief {
  id: number;
  batchnum: string;
  startdate?: string | null;
  enddate?: string | null;
}

export interface CertificateSessionBrief {
  id: number;
  sessionName?: string;
  sessionname?: string;
  year?: string | number;
}

/**
 * Institutional (ICBD) Certificate Entity
 */
export interface CertificateIcbd {
  id: number;
  student_id: number;
  course_id: number;
  batch_id: number;
  certificate_no: string;
  theory_hours?: number | null;
  practical_hours?: number | null;
  intern_hours?: number | null;
  result?: string | null;
  issue_date?: string | null;
  certificate_receiver?: string | null;
  delivery_date?: string | null;
  is_active: number;
  cimage?: string | null;
  dimage?: string | null;
  status_label?: string;
  total_hours?: number;
  verification_url?: string;
  qr_code_url?: string;
  pdf_download_url?: string;
  print_url?: string;
  created_at?: string;
  updated_at?: string;
  student?: CertificateStudentBrief | null;
  course?: CertificateCourseBrief | null;
  batch?: CertificateBatchBrief | null;
}

/**
 * Technical Board (BTEB) Certificate Entity
 */
export interface CertificateBteb {
  id: number;
  student_id: number;
  session_id: number;
  regNo: string;
  cno?: string | null;
  pDate?: string | null;
  recevier?: string | null;
  cimage?: string | null;
  dimage?: string | null;
  Result?: string | null;
  dDate?: string | null;
  IsActive: number;
  created_at?: string;
  updated_at?: string;
  StudentsInfo?: CertificateStudentBrief | null;
  student?: CertificateStudentBrief | null;
  StudentsSessions?: CertificateSessionBrief | null;
}

/**
 * Student Eligibility Assessment Response
 */
export interface CertificateEligibility {
  eligible: boolean;
  can_issue_icbd: boolean;
  can_issue_bteb: boolean;
  has_outstanding_dues: boolean;
  student: {
    id: number;
    name: string;
    phone?: string | null;
    nid?: string | null;
    course?: { id: number; name: string } | null;
    batch?: { id: number; batchnum: string } | null;
    balance: string;
    is_active: number;
    is_registered: number;
  };
  existing_icbd_certificate?: {
    id: number;
    certificate_no: string;
    issue_date?: string | null;
    status: string;
  } | null;
  existing_bteb_certificate?: {
    id: number;
    cno?: string | null;
    regNo: string;
    passing?: string | null;
  } | null;
  reasons: string[];
}

/**
 * Public Certificate Verification Payload (Data Minimized)
 */
export interface PublicVerificationResult {
  is_valid: boolean;
  status: string;
  certificate_type: string;
  certificate_no?: string;
  registration_no?: string;
  student_name: string;
  course_name?: string;
  course_duration?: string;
  session?: string;
  curriculum_hours?: {
    classroom_theory: number;
    practical_lab: number;
    internship: number;
    total_course_hours: number;
  };
  result?: string;
  issue_date?: string | null;
  passing_date?: string | null;
  institution: {
    name: string;
    tagline?: string;
    website: string;
  };
  verification_url?: string;
  verified_at: string;
}

/**
 * Aggregated Certificate Summary Report
 */
export interface CertificateSummaryReport {
  icbd_certificates: {
    total: number;
    delivered: number;
    processing: number;
    printing_queue: number;
    revoked: number;
  };
  bteb_certificates: {
    total: number;
    active: number;
  };
  course_distribution: Record<string, number>;
}

/**
 * ICBD Status Map & Metadata
 */
export const ICBD_STATUS_MAP: Record<
  number,
  { code: number; label: string; badgeClass: string; color: string }
> = {
  1: { code: 1, label: 'Delivered', badgeClass: 'innova-badge-success', color: 'var(--innova-success)' },
  2: { code: 2, label: 'Processing', badgeClass: 'innova-badge-info', color: 'var(--innova-info)' },
  3: { code: 3, label: 'Printing Queue', badgeClass: 'innova-badge-warning', color: 'var(--innova-warning)' },
  4: { code: 4, label: 'Revoked', badgeClass: 'innova-badge-error', color: 'var(--innova-error)' },
};

/**
 * BTEB Status Map & Metadata
 */
export const BTEB_STATUS_MAP: Record<
  number,
  { code: number; label: string; badgeClass: string; color: string }
> = {
  1: { code: 1, label: 'Active', badgeClass: 'innova-badge-success', color: 'var(--innova-success)' },
  0: { code: 0, label: 'Inactive / Revoked', badgeClass: 'innova-badge-error', color: 'var(--innova-error)' },
};

/**
 * ICBD Certificate Form Data
 */
export interface IcbdFormData {
  student_id: number;
  course_id: number;
  batch_id: number;
  certificate_no?: string;
  theory_hours?: number;
  practical_hours?: number;
  intern_hours?: number;
  result?: string;
  issue_date?: string;
  certificate_receiver?: string;
  delivery_date?: string;
  is_active: number;
}

/**
 * BTEB Certificate Form Data
 */
export interface BtebFormData {
  student_id: number;
  session_id: number;
  regNo: string;
  cno?: string;
  pDate?: string;
  Result?: string;
  recevier?: string;
  dDate?: string;
  IsActive: number;
}

/**
 * Search & Filter Options
 */
export interface CertificateFilters {
  search?: string;
  course_id?: number | string;
  batch_id?: number | string;
  session_id?: number | string;
  is_active?: number | string;
  result?: string;
  start_date?: string;
  end_date?: string;
  page?: number;
  per_page?: number;
}
