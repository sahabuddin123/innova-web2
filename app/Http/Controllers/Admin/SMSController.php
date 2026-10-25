<?php

namespace App\Http\Controllers\Admin;

use App\Models\Students;
use App\Models\Courses;
use App\Models\Batchs;
use App\Models\FeesTransections;
use App\Contracts\StudentsContract;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use DB;

class SMSController extends BaseController
{
    // For sms Settings
    public function send_sms($phone, $msg)
    {
        return app(\App\Services\SmsService::class)->sendRaw($phone, $msg);
    }

    // Today class Postpond SMS
    public function todayPostpond($id)
    {
        $students = Students::where('id', $id)->first();
        $phone = $students['phone'];
        $msg = "প্রিয় $students[sname], অনিার্য কারণ বত আপনার আজকর ক্লাসটি সথগিত করা হয়ছে। বিস্তািত তথ্যের জ্য যোগাযোগ রুন - 01844463954। ধ্যবাদ, ইনোভ কম্পিউটারস বিডি।";
        $sendsms = $this->send_sms($phone, $msg);
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
        }
        return response()->json(['success' => $students['sname'] . '! Postponded Send Send Successfully.']);
    }
    // Tomorrow class Postpond SMS
    public function tomorrowPostpond($id)
    {
        $students = Students::where('id', $id)->first();
        $phone = $students['phone'];
        $msg = "প্রিয় $students[sname], নিবার্য কাণ বসত আপনার গামীকালকের ক্লাসটি স্থগিত করা হয়েছে। বিস্তারি তথ্যের জন্ যোগাযোগ করন - 01844463954। ধন্যবাদ, ইনোভা ম্পিউটার্স বিডি।";
        $sendsms = $this->send_sms($phone, $msg);
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
        }
        return response()->json(['success' => $students['sname'] . '! Postponded Send Send Successfully.']);
    }
    // Student Course Fee Due
    public function dueSMS($id)
    {
        $students = Students::where('id', $id)->first();
        $pay = FeesTransections::where('student_id',  $id)->where('paymentType', 1)->sum('paidamount');
        $due = $students['balance'] - $pay;
        $finalDue = number_format($due, 2);
        $phone = $students['phone'];
        $msg = "প্রিয় $students[sname], আপনার ব্তমান কোর্স ফি বকেয়াঃ $finalDue টাকা। অনু্রহপূর্বক কোর্স ফি পরিশোধ করুন। ধন্যবাদ, ইনোভা ম্পিউটার্স বিডি।";
        $sendsms = $this->send_sms($phone, $msg);
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
            // return $this->responseRedirectBack('Error occurred while Due SMS Sending Fail.', 'error', true, true);
        }
        return response()->json(['success' => $students['sname'] . '! Due Reaminder Send Successfully.']);
        // return $this->responseRedirect('admin.students.index', 'Due SMS Send successfully' ,'success',false, false);

    }
    // New Class Schedule
    public function scheduleSMS($id)
    {
        $students = Students::where('id', $id)->first();
        $batchs = Batchs::where('id', $students['batch_id'])->first();
        if ($batchs->day1 == 1) {
            $schedule1 = $batchs->schedule1;
            $day1 = "Friday : $schedule1, ";
        } else {
            $day1 = "";
        }
        if ($batchs->day2 == 1) {
            $schedule2 = $batchs->schedule2;
            $day2 = "Saturday : $schedule2, ";
        } else {
            $day2 = "";
        }
        if ($batchs->day3 == 1) {
            $schedule3 = $batchs->schedule3;
            $day3 = "Sunday : $schedule3, ";
        } else {
            $day3 = "";
        }
        if ($batchs->day4 == 1) {
            $schedule4 = $batchs->schedule4;
            $day4 = "Monday : $schedule4, ";
        } else {
            $day4 = "";
        }
        if ($batchs->day5 == 1) {
            $schedule5 = $batchs->schedule5;
            $day5 = "Tuesday : $schedule5, ";
        } else {
            $day5 = "";
        }
        if ($batchs->day6 == 1) {
            $schedule6 = $batchs->schedule6;
            $day6 = "Wednesday : $schedule6, ";
        } else {
            $day6 = "";
        }
        if ($batchs->day7 == 1) {
            $schedule7 = $batchs->schedule7;
            $day7 = "Thursday : $schedule7, ";
        } else {
            $day7 = "";
        }
        $scheduleClass = "Dear $students[sname], Your New Class Schedule is - $day1 $day2 $day3 $day4 $day5 $day6 $day7 Thanks - INNOVA Computers BD.";

        $phone = $students['phone'];
        $sendsms = $this->send_sms($phone, $scheduleClass);
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
        }
        return response()->json(['success' => $students['sname'] . '! New Class Schedule Send Successfully.']);
    }

    // Today Class Remainder
    public function remainderSMS($id)
    {
        $students = Students::where('id', $id)->first();
        $batchs = Batchs::where('id', $students['batch_id'])->first();
        $course = Courses::where('id', $students['course_id'])->first();
        $today = date('w');
        $todate = date('d-M-Y');
        $schedule = "";
        if ($today == 0) {
            $schedule = $batchs->schedule3;
        } else if ($today == 1) {
            $schedule = $batchs->schedule4;
        } else if ($today == 2) {
            $schedule = $batchs->schedule5;
        } else if ($today == 3) {
            $schedule = $batchs->schedule6;
        } else if ($today == 4) {
            $schedule = $batchs->schedule7;
        } else if ($today == 5) {
            $schedule = $batchs->schedule1;
        } else if ($today == 6) {
            $schedule = $batchs->schedule2;
        }

        $phone = $students['phone'];
        $msg = "প্রিয় $students[sname], আজকে $todate, তারিখ $schedule পনার $course->name কোর্সের ক্লা আছে। অনুগ্হপূর্বক ক্াসে উপস্থিত থাকবেন। ধন্যবাদ, ইনোভা ম্পিউটার্স বিডি।";
        $sendsms = $this->send_sms($phone, $msg);
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
        }
        return response()->json(['success' => $students['sname'] . '! Class Reaminder Send Successfully.']);
    }
    // group Due SMS
    public function groupDueSMS($id)
    {
        // select batch wise Student
        $student = Students::where('batch_id', $id)->where('IsActive', '=', 1)->orWhere('IsActive', '=', 2)->where('deleted_at', null)->orderBy('id', 'desc')->get();
        foreach ($student as $students) {
            $pay = FeesTransections::where('student_id',  $students['id'])->where('paymentType', 1)->sum('paidamount');
            $due = $students['balance'] - $pay;
            $finalDue = number_format($due, 2);
            $phone = $students['phone'];
            $msg = "প্রিয় $students[sname], আপনার বর্তান কোর্স ফি বকেয়াঃ $finalDue টাকা। অনুগ্রপূর্বক কোরস ফি পরিশোধ রুন। ধন্যবদ, ইনোভা কম্িউটার্স বিি।";
            $sendsms = $this->send_sms($phone, $msg);
        }
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
        }
        return response()->json(['success' => 'Due SMS Send Successfully.']);
    }

    // Group Today Postpond SMS
    public function groupTdyPSMS($id)
    {
        // select batch wise Student
        $student = Students::where('batch_id', $id)->where('IsActive', '=', 1)->orWhere('IsActive', '=', 2)->where('deleted_at', null)->orderBy('id', 'desc')->get();
        foreach ($student as $students) {
            $phone = $students['phone'];
            $msg = "প্রিয় $students[sname], নিবার্য কাণ বসত আপনার জকের ক্লাসি স্থগিত কর হয়েছে। বিসতারিত তথ্যের জন্য যোগাযোগ করুন - 01844463954। ধন্যবাদ, ইোভা কম্পিউার্স বিডি।";
            $sendsms = $this->send_sms($phone, $msg);
        }
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
        }
        return response()->json(['success' => 'Today Class Postpond SMS Send Successfully.']);
    }

    // Group Tomorrow Postpond SMS
    public function groupTomPSMS($id)
    {
        // select batch wise Student
        $student = Students::where('batch_id', $id)->where('IsActive', '=', 1)->orWhere('IsActive', '=', 2)->where('deleted_at', null)->orderBy('id', 'desc')->get();
        foreach ($student as $students) {
            $phone = $students['phone'];
            $msg = "প্রিয় $students[sname], অনিবার্ কারণ বসত আপার আগামীকাকের ক্লাসটি স্থগিত করা য়েছে। বিস্তারিত তথ্যের জন্য যোগাযো করুন - 01844463954। ধন্যবাদ, ইনভা কম্পিউটার্স বিডি।";
            $sendsms = $this->send_sms($phone, $msg);
        }
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
        }
        return response()->json(['success' => 'Tomorrow Class Postpond SMS Send Successfully.']);
    }
    // group today Class remainder
    public function groupTCR($id)
    {
        // select batch wise Student
        $student = Students::where('batch_id', $id)->where('IsActive', '=', 1)->orWhere('IsActive', '=', 2)->where('deleted_at', null)->orderBy('id', 'desc')->get();
        foreach ($student as $students) {
            $batchs = Batchs::where('id', $students['batch_id'])->first();
            $course = Courses::where('id', $students['course_id'])->first();
            $today = date('w');
            $todate = date('d-M-Y');
            $schedule = "";
            if ($today == 0) {
                $schedule = $batchs->schedule3;
            } else if ($today == 1) {
                $schedule = $batchs->schedule4;
            } else if ($today == 2) {
                $schedule = $batchs->schedule5;
            } else if ($today == 3) {
                $schedule = $batchs->schedule6;
            } else if ($today == 4) {
                $schedule = $batchs->schedule7;
            } else if ($today == 5) {
                $schedule = $batchs->schedule1;
            } else if ($today == 6) {
                $schedule = $batchs->schedule2;
            }

            $phone = $students['phone'];
            $msg = "পরিয় $students[sname], জকে $todate, তারিখ $schedule আপনার $course->name কোর্সে ক্লাস আছে। নুগ্রহপূর্বক ক্লাসে উপস্থিত থাকবে। ধন্যবাদ, ইনোভা কম্পিউার্স বিডি।";
            $sendsms = $this->send_sms($phone, $msg);
        }
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
        }
        return response()->json(['success' => 'Tomorrow Class Postpond SMS Send Successfully.']);
    }
    // group new class Schdule
    public function groupNCS($id)
    {
        // select batch wise Student
        $student = Students::where('batch_id', $id)->where('IsActive', '=', 1)->orWhere('IsActive', '=', 2)->where('deleted_at', null)->orderBy('id', 'desc')->get();
        foreach ($student as $students) {
            $batchs = Batchs::where('id', $students['batch_id'])->first();
            if ($batchs->day1 == 1) {
                $schedule1 = $batchs->schedule1;
                $day1 = "Friday : $schedule1, ";
            } else {
                $day1 = "";
            }
            if ($batchs->day2 == 1) {
                $schedule2 = $batchs->schedule2;
                $day2 = "Saturday : $schedule2, ";
            } else {
                $day2 = "";
            }
            if ($batchs->day3 == 1) {
                $schedule3 = $batchs->schedule3;
                $day3 = "Sunday : $schedule3, ";
            } else {
                $day3 = "";
            }
            if ($batchs->day4 == 1) {
                $schedule4 = $batchs->schedule4;
                $day4 = "Monday : $schedule4, ";
            } else {
                $day4 = "";
            }
            if ($batchs->day5 == 1) {
                $schedule5 = $batchs->schedule5;
                $day5 = "Tuesday : $schedule5, ";
            } else {
                $day5 = "";
            }
            if ($batchs->day6 == 1) {
                $schedule6 = $batchs->schedule6;
                $day6 = "Wednesday : $schedule6, ";
            } else {
                $day6 = "";
            }
            if ($batchs->day7 == 1) {
                $schedule7 = $batchs->schedule7;
                $day7 = "Thursday : $schedule7, ";
            } else {
                $day7 = "";
            }
            $scheduleClass = "Dear $students[sname], Your New Class Schedule is - $day1 $day2 $day3 $day4 $day5 $day6 $day7 Thanks - INNOVA Computers BD.";

            $phone = $students['phone'];
            $sendsms = $this->send_sms($phone, $scheduleClass);
        }
        if (!$sendsms) {
            return response()->json(['error' => 'Error occurred while  SMS Sending Fail.']);
        }
        return response()->json(['success' => 'Tomorrow Class Postpond SMS Send Successfully.']);
    }
}
