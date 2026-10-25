<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Josefin+Sans:ital,wght@0,100..700;1,100..700&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');
        body,
        html {
            margin: 0;
            padding: 0;
            font-family: "Josefin Sans", sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: transparent;

        }
        .certificate-container {
            width: 29.7cm;
            height: 21cm;
            position: relative;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            box-sizing: border-box;
            background: url("{!! asset('./backend/img/certificate/certificate-background-two.png') !!}") no-repeat center center;
            background-size: cover;
        }

        .content {
            margin: 20px 20px 20px 80px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
        }
        .header-right{
                margin-top: -48px;
        }
        .logo {
            height: 80px;
            /* margin-right: 20px; */
            margin-left: 140px;
            margin-top: -15px;
        }

        .title-section h1 {
            font-size: 30px;
            text-transform: uppercase;
            margin-left: 150px;
            margin-top: 26px;
            letter-spacing: 1.6px;
        }

        .qr {
            height: 80px;
            margin-right: 0;
            margin-top: -100px;
        }

        .acknowledgment p:nth-child(1) {
            font-family: "Poppins", sans-serif;
            text-align: left;
            /* margin-left: 77px; */
            margin-top: 19px;
            font-weight: 600;
        }

        .text-lower {
            text-transform: lowercase;
        }

        .recipient-name h1 {
            font-size: 46px;
            margin-left: -3px;
            margin-top: 22px;
            letter-spacing: 1px;
            margin-bottom: 0;
        }

        .success_text {
            font-size: 16px;
            margin-top: 23px;
            font-weight: 500;
            margin-left: -2px;
            font-family: "Poppins", sans-serif;
        }

        .certification-level h3 {
                font-size: 30px;
    margin-left: 0px;
    letter-spacing: 2.3px;
    margin-bottom: 0;
    margin-top: 19px;
        }

        .details p {
            margin-top: 18px;
            font-size: 15px;
            font-family: 'poppins', sans-serif;
            width: 90%;
        }

        .text_line {
            width: 96%;
            border-top: 2px solid #000;
            margin-left: -3px;
            margin-top: 10px;
        }

        .text_line_two {
            width: 80%;
            border-top: 2px solid #000;
            margin-left: -4px;
            margin-top: -4px;
        }

        .hours_P {
            font-size: 12px;
            font-family: 'poppins';
            font-weight: 600;
            margin-top: 2px;
            letter-spacing: -0.5px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 72%;
            margin-top: 61px;

        }

        .signature_director h4 {
            margin-top: -8px;
            font-size: 20px;
            text-transform: uppercase;
            margin-bottom: -5px;
        }

        .signature_director p {
            font-size: 12px;
            font-family: 'poppins';
            font-weight: 600;
            margin-top: 2px;
            letter-spacing: -0.5px;
        }
        .issus_date{
            margin-left: 82px;
        }
        .text-center {
            text-align: center;
        }   
        .issus_date h4,
        .certificate_id h4 {
            margin-top: -2px;
            font-size: 15px;
            text-transform: uppercase;
            margin-bottom: -5px;
            font-weight: 700;
        }
        .issus_date p,
        .certificate_id p {
            font-size: 12px;
            font-family: 'poppins';
            font-weight: 600;
            margin-top: 2px;
            letter-spacing: -0.5px;
        }
        .class-hours{
            margin-top: 35px;
        }
        @media print {
            body{
                background-color: transparent !important;
            }
            body,  html, .certificate-container {
                box-shadow: none !important;
                margin: 0;
            }

        }
    </style>
</head>

<body>
    <div class="certificate-container">
        <div class="content">
            <div class="header">
                <div class="header-left">
                    <div class="title-section">
                        <img src="{!! asset('./backend/img/certificate/icbd_new_logos.png') !!}" alt="Logo" class="logo">
                        <h1>Computer course complete<br> <span>CERTIFICATION</span></h1>

                    </div>
                </div>
                <div class="header-right">
                    @php
                        if (empty($qrCodeData)) {
                            try {
                                $qrCodeData = 'data:image/png;base64,' . base64_encode(
                                    QrCode::format('png')->size(150)->generate(url('/certifiocate-verify/' . $certificate->certificate_no))
                                );
                            } catch (\Throwable $e) {
                                $qrCodeData = 'data:image/svg+xml;base64,' . base64_encode(
                                    QrCode::format('svg')->size(150)->generate(url('/certifiocate-verify/' . $certificate->certificate_no))
                                );
                            }
                        }
                    @endphp
                    <img src="{{ $qrCodeData }}" alt="QR" class="qr">
                </div>
            </div>

            <div class="acknowledgment">
                <p>This is to acknowledge that</p>
                <div class="recipient-name">
                    <h1 class="text-upper">{{$certificate->student->sname}}</h1>
                    <div class="text_line"></div>
                </div>
                <p class="success_text">has successfully met the requirements and has advanced to the</p>
            </div>

            <div class="certification-level">
                <h3>{{ $certificate->course->name }} </h3>
            </div>

            <div class="details">
                <p>This certification is issued in recognition of the successful completion of the course, training, and examination necessary to perform the duties of a <span>{{ $certificate->course->name }}.</span> </p>
                <h4>We wish you all the best for your future.</h4>
            </div>
            <div class="class-hours">
                <p>CLASSROOM: {{ $certificate->theory_hours }} HOURS, PRACTICAL: {{ $certificate->practical_hours }} HOURS, INTERNSHIP: {{ $certificate->intern_hours }} HOURS.</p>
                <div class="text_line_two"></div>
                <p class="hours_P">Course Hours</p>
            </div>

            <div class="signatures">
                <div class="signature_director">
                    <!-- <img src="signature-2.png" alt="Program Director Signature" class="signature-img"> -->
                    <h4>Sahab Uddin</h4>
                    <div class="text_line" style="width: 121%; margin-top: 11px;"></div>
                    <p>Executive Director</p>
                </div>
                <div class="issus_date">
                    <h4 class="text-center">
                       {{ date('Y-m-d',strtotime($certificate->issue_date))}}
                    </h4>
                    <div class="text_line" style="width: 200%; margin-top: 511x;"></div>
                    <p>DATE OF ISSUE</p>
                </div>
                <div class="certificate_id">
                    <h4 class="text-center">
                        {{ $certificate->certificate_no }}
                    </h4>
                    <div class="text_line" style="width: 200%; margin-top: 11px;"></div>
                    <p>CERTIFICATE ID</p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>