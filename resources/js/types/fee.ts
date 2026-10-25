/**
 * INNOVA WEB — Fees & Payment Types & Contracts
 *
 * Defines TypeScript interfaces and constants for student fees,
 * counter transactions, bKash payments, balances, and receipts.
 */

export type FeePaymentType = 1 | 2; // 1 = Course Fee, 2 = Registration Fee
export type FeeChannel = 1 | 2;     // 1 = Cash Counter, 2 = bKash Gateway
export type FeeStatus = 0 | 1;      // 0 = Approved, 1 = Pending Approval

export interface FeeStudentBrief {
  id: number;
  user_id?: number | null;
  sname: string;
  phone: string;
  course_id?: number;
  batch_id?: number;
  course_fee?: string | number;
  balance?: string | number;
  image?: string | null;
  course?: {
    id: number;
    name: string;
    shortTitle?: string;
  };
  batch?: {
    id: number;
    batchnum: string;
  };
}

export interface FeeUserBrief {
  id: number;
  name: string;
  email: string;
}

export interface FeeTransaction {
  id: number;
  user_id?: number | null;
  student_id: number;
  paidamount: string | number;
  paymentType: number | string; // 1 = Course Fee, 2 = Registration Fee
  paiddate: string;
  paidremark: string | null;
  channel: number | null;       // 1 = Cash Counter, 2 = bKash Gateway
  trxID: string | null;
  paymentID: string | null;
  status: number | null;        // 0 = Approved, 1 = Pending
  created_at?: string;
  updated_at?: string;
  student?: FeeStudentBrief;
  user?: FeeUserBrief;
}

export interface FeeFinancialSummary {
  student_id: number;
  total_course_fee: number;
  discount: number;
  net_payable: number;
  total_paid: number;
  due_amount: number;
}

export interface StudentFeeAccountSummary {
  total_course_fee: number;
  discount: number;
  net_payable: number;
  total_paid_course: number;
  total_paid_registration: number;
  due_course_fee: number;
}

export interface StudentFeeBalanceResponse {
  student: {
    id: number;
    name: string;
    phone: string;
    course_name: string | null;
    batch_num: string | null;
  };
  account_summary: StudentFeeAccountSummary;
  transactions: FeeTransaction[];
}

export interface FeeReceiptData {
  receipt_number: string;
  transaction_id: number;
  paid_date: string;
  channel: string;
  payment_type: string;
  status: string;
  paid_amount: number;
  amount_in_words: string;
  paid_remark: string | null;
  trx_id: string | null;
  student: {
    id: number | null;
    name: string | null;
    phone: string | null;
    course_name: string | null;
    batch_num: string | null;
  };
  account_summary: {
    total_course_fee: number;
    discount: number;
    net_payable: number;
    total_paid: number;
    net_due: number;
  };
}

export interface StoreFeeTransactionPayload {
  student_id: number;
  paidamount: number | string;
  paymentType: number | string;
  paiddate: string;
  paidremark: string;
  channel?: number;
}

export interface UpdateFeeTransactionPayload {
  paidamount: number | string;
  paymentType: number | string;
  paiddate: string;
  paidremark: string;
}

export interface UpdateFeeStatusPayload {
  status: number;
}

export interface FeeFilters {
  student_id?: number;
  payment_type?: string | number;
  channel?: number;
  status?: number;
  start_date?: string;
  end_date?: string;
  trx_id?: string;
  page?: number;
  per_page?: number;
}

export interface BkashQueryResult {
  paymentID?: string;
  trxID?: string;
  transactionStatus?: string;
  amount?: string | number;
  currency?: string;
  customerMsisdn?: string;
  paymentExecuteTime?: string;
  merchantInvoiceNumber?: string;
  orgLogo?: string;
  orgName?: string;
  [key: string]: unknown;
}

export interface FeeSummaryReport {
  total_collected: number;
  total_transactions: number;
  pending_count: number;
  approved_count: number;
  cash_collected: number;
  bkash_collected: number;
}

export const FEE_STATUS_MAP: Record<number, { label: string; badgeClass: string }> = {
  0: { label: 'Approved', badgeClass: 'innova-badge-success' },
  1: { label: 'Pending Approval', badgeClass: 'innova-badge-warning' },
};

export const FEE_CHANNEL_MAP: Record<number, { label: string; badgeClass: string }> = {
  1: { label: 'Cash Counter', badgeClass: 'innova-badge-neutral' },
  2: { label: 'bKash Gateway', badgeClass: 'innova-badge-info' },
};

export const FEE_TYPE_MAP: Record<number, string> = {
  1: 'Course Fee',
  2: 'Registration Fee',
};
