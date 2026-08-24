<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\EmployeeExperience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FileStreamController extends Controller
{
    /**
     * Resolve a file from multiple possible storage directories.
     */
    protected function findFileOnDisk(string $path): ?string
    {
        $cleanPath = ltrim(str_replace(['\\', '..'], '/', $path), '/');

        // Strip prefixes if redundantly passed
        $relativePaths = [
            $cleanPath,
            preg_replace('#^(public/|uploads/|storage/)#i', '', $cleanPath),
            'uploads/' . preg_replace('#^(public/|uploads/)#i', '', $cleanPath),
            'employee_certificates/' . basename($cleanPath),
            'guarantee_letters/' . basename($cleanPath),
            'employee_licenses/' . basename($cleanPath),
            'employees/' . basename($cleanPath),
            'correspondence/' . basename($cleanPath),
            'daily_reports/' . basename($cleanPath),
        ];

        $possibleRoots = [
            public_path('uploads'),
            public_path(),
            base_path('uploads'),
            storage_path('app/public'),
            storage_path('app'),
            public_path('storage'),
            base_path('public/uploads'),
            base_path('public/storage'),
            base_path('storage/app/public'),
            base_path('storage/app'),
        ];

        foreach ($possibleRoots as $root) {
            foreach ($relativePaths as $rel) {
                if (empty($rel)) continue;
                $candidate = rtrim($root, '/') . '/' . ltrim($rel, '/');
                if (file_exists($candidate) && is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Stream any uploaded file.
     */
    public function streamUpload(Request $request, $path)
    {
        return $this->respondWithFile($path, 'uploads');
    }

    /**
     * Stream any storage file.
     */
    public function streamStorage(Request $request, $path)
    {
        return $this->respondWithFile($path, 'storage');
    }

    /**
     * Dedicated route to view an employee certificate.
     */
    public function viewCertificate($id)
    {
        $edu = EmployeeEducation::find($id);

        if (!$edu || empty($edu->certificate_photo)) {
            return $this->renderMissingDocument('Educational Certificate', 'No certificate document is recorded for this education entry.');
        }

        if (Str::startsWith($edu->certificate_photo, ['http://', 'https://'])) {
            return redirect()->away($edu->certificate_photo);
        }

        return $this->respondWithFile($edu->certificate_photo, 'uploads', 'Certificate - ' . ($edu->degree_level ?? 'Document'));
    }

    /**
     * Dedicated route to view an employee license (from dedicated employee_licenses table).
     */
    public function viewLicenseDocument($id)
    {
        $license = \App\Models\EmployeeLicense::find($id);

        if (!$license || empty($license->license_document)) {
            // Fallback check in legacy experience table
            $exp = EmployeeExperience::find($id);
            if ($exp && !empty($exp->license_document)) {
                if (Str::startsWith($exp->license_document, ['http://', 'https://'])) {
                    return redirect()->away($exp->license_document);
                }
                return $this->respondWithFile($exp->license_document, 'uploads', 'License - ' . ($exp->license_number ?? 'Document'));
            }
            return $this->renderMissingDocument('Professional License', 'No license document is recorded for this license entry.');
        }

        if (Str::startsWith($license->license_document, ['http://', 'https://'])) {
            return redirect()->away($license->license_document);
        }

        return $this->respondWithFile($license->license_document, 'uploads', 'License - ' . ($license->license_name ?? 'Document'));
    }

    /**
     * Dedicated route to view an employee license (legacy experience entry).
     */
    public function viewLicense($id)
    {
        $exp = EmployeeExperience::find($id);

        if (!$exp || empty($exp->license_document)) {
            return $this->renderMissingDocument('Professional License', 'No license document is recorded for this experience entry.');
        }

        if (Str::startsWith($exp->license_document, ['http://', 'https://'])) {
            return redirect()->away($exp->license_document);
        }

        return $this->respondWithFile($exp->license_document, 'uploads', 'License - ' . ($exp->license_number ?? 'Document'));
    }

    /**
     * Dedicated route to view an employee experience letter / certificate.
     */
    public function viewExperienceLetter($id)
    {
        $exp = EmployeeExperience::find($id);

        if (!$exp || empty($exp->experience_letter)) {
            return $this->renderMissingDocument('Experience Certificate', 'No experience certificate or letter is recorded for this experience entry.');
        }

        if (Str::startsWith($exp->experience_letter, ['http://', 'https://'])) {
            return redirect()->away($exp->experience_letter);
        }

        return $this->respondWithFile($exp->experience_letter, 'uploads', 'Experience Certificate - ' . ($exp->company_name ?? 'Document'));
    }

    /**
     * Dedicated route to view an employee guarantee letter.
     */
    public function viewGuaranteeLetter($id)
    {
        $emp = Employee::find($id);

        if (!$emp || empty($emp->guarantee_letter)) {
            return $this->renderMissingDocument('Guarantee Letter', 'No guarantee letter has been uploaded for this employee.');
        }

        if (Str::startsWith($emp->guarantee_letter, ['http://', 'https://'])) {
            return redirect()->away($emp->guarantee_letter);
        }

        return $this->respondWithFile($emp->guarantee_letter, 'uploads', 'Guarantee Letter - ' . $emp->full_name);
    }

    /**
     * Dedicated route to view an employee National ID card / scan / photo.
     */
    public function viewNationalIdCard($id)
    {
        $emp = Employee::find($id);

        if (!$emp || empty($emp->national_id_card)) {
            return $this->renderMissingDocument('National ID Card', 'No National ID document or photo is on file for this employee.');
        }

        if (Str::startsWith($emp->national_id_card, ['http://', 'https://'])) {
            return redirect()->away($emp->national_id_card);
        }

        return $this->respondWithFile($emp->national_id_card, 'uploads', 'National ID - ' . $emp->full_name);
    }

    /**
     * Dedicated route to view an employee asset handover document / receipt.
     */
    public function viewAssetHandoverDocument($id)
    {
        $emp = Employee::find($id);

        if (!$emp || empty($emp->asset_handover_document)) {
            return $this->renderMissingDocument('Asset Handover Document', 'No asset handover receipt or condition photo is on file for this employee.');
        }

        if (Str::startsWith($emp->asset_handover_document, ['http://', 'https://'])) {
            return redirect()->away($emp->asset_handover_document);
        }

        return $this->respondWithFile($emp->asset_handover_document, 'uploads', 'Asset Handover - ' . $emp->full_name);
    }

    /**
     * Dedicated route to view an employee profile picture / avatar.
     */
    public function viewProfilePicture($id)
    {
        $emp = Employee::find($id);

        if (!$emp || empty($emp->profile_picture)) {
            $name = urlencode($emp->full_name ?? 'Employee');
            return redirect()->away("https://ui-avatars.com/api/?name={$name}&background=198754&color=fff&size=200&bold=true");
        }

        if (Str::startsWith($emp->profile_picture, ['http://', 'https://'])) {
            return redirect()->away($emp->profile_picture);
        }

        return $this->respondWithFile($emp->profile_picture, 'uploads', 'Profile Photo - ' . $emp->full_name);
    }

    /**
     * Dedicated route to view an employee registration letter / contract (single or multi-page).
     */
    public function viewRegistrationLetter($id, Request $request = null)
    {
        $emp = Employee::find($id);

        if (!$emp) {
            return $this->renderMissingDocument('Registration Letter', 'Employee not found.');
        }

        $index = $request ? (int) $request->query('index', 0) : 0;
        $file = null;

        // If stored as JSON array in registration_letters or registration_letter
        $letters = $emp->registration_letters;
        if (is_array($letters) && count($letters) > 0) {
            $file = $letters[$index] ?? $letters[0];
        } elseif (!empty($emp->registration_letter)) {
            $decoded = json_decode($emp->registration_letter, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $file = $decoded[$index] ?? $decoded[0];
            } else {
                $file = $emp->registration_letter;
            }
        }

        if (empty($file)) {
            return $this->renderMissingDocument('Registration Letter', 'No registration letter or employment contract document is on file for this employee.');
        }

        if (Str::startsWith($file, ['http://', 'https://'])) {
            return redirect()->away($file);
        }

        $suffix = $index > 0 ? ' (Page ' . ($index + 1) . ')' : '';
        return $this->respondWithFile($file, 'uploads', 'Registration Letter' . $suffix . ' - ' . $emp->full_name);
    }

    /**
     * Dedicated route to view an employee primary guarantor ID card / document.
     */
    public function viewGuarantorId($id)
    {
        $emp = Employee::find($id);

        if (!$emp || empty($emp->guarantor_id_card)) {
            return $this->renderMissingDocument('Guarantor 1 National ID Document', 'No primary guarantor ID card or document is on file for this employee.');
        }

        if (Str::startsWith($emp->guarantor_id_card, ['http://', 'https://'])) {
            return redirect()->away($emp->guarantor_id_card);
        }

        return $this->respondWithFile($emp->guarantor_id_card, 'uploads', 'Guarantor 1 ID - ' . ($emp->guarantor_name ?: $emp->full_name));
    }

    /**
     * Dedicated route to view an employee second guarantor ID card / document.
     */
    public function viewGuarantor2Id($id)
    {
        $emp = Employee::find($id);

        if (!$emp || empty($emp->guarantor_2_id_card)) {
            return $this->renderMissingDocument('Guarantor 2 National ID Document', 'No second guarantor ID card or document is on file for this employee.');
        }

        if (Str::startsWith($emp->guarantor_2_id_card, ['http://', 'https://'])) {
            return redirect()->away($emp->guarantor_2_id_card);
        }

        return $this->respondWithFile($emp->guarantor_2_id_card, 'uploads', 'Guarantor 2 ID - ' . ($emp->guarantor_2_name ?: $emp->full_name));
    }

    /**
     * Dedicated route to view an employee second guarantee letter.
     */
    public function viewGuaranteeLetter2($id)
    {
        $emp = Employee::find($id);

        if (!$emp || empty($emp->guarantee_letter_2)) {
            return $this->renderMissingDocument('Second Guarantee Letter', 'No second guarantee letter has been uploaded for this employee.');
        }

        if (Str::startsWith($emp->guarantee_letter_2, ['http://', 'https://'])) {
            return redirect()->away($emp->guarantee_letter_2);
        }

        return $this->respondWithFile($emp->guarantee_letter_2, 'uploads', 'Second Guarantee Letter - ' . $emp->full_name);
    }

    /**
     * Return file response or friendly fallback page.
     */
    protected function respondWithFile(string $path, string $prefix = 'uploads', ?string $customTitle = null)
    {
        $filePath = $this->findFileOnDisk($path);

        if ($filePath && file_exists($filePath) && is_file($filePath)) {
            $mimeType = File::mimeType($filePath) ?: 'application/octet-stream';
            $filename = basename($filePath);

            return response()->file($filePath, [
                'Content-Type'        => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Cache-Control'       => 'public, max-age=86400',
            ]);
        }

        return $this->renderMissingDocument($customTitle ?: basename($path), "The file '{$path}' was not found on the live server storage. Please re-upload the document.");
    }

    /**
     * Render an elegant missing document notice instead of a 404 crash.
     */
    protected function renderMissingDocument(string $title, string $message)
    {
        $html = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>{$title} - Document Notice</title>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
        </head>
        <body class='bg-light d-flex align-items-center justify-content-center' style='min-height: 100vh; font-family: system-ui, sans-serif;'>
            <div class='card shadow-lg border-0 rounded-4 p-4 text-center' style='max-width: 520px;'>
                <div class='mb-3'>
                    <div class='d-inline-flex p-3 rounded-circle bg-warning bg-opacity-10 text-warning'>
                        <i class='fa-solid fa-file-circle-question fa-3x'></i>
                    </div>
                </div>
                <h4 class='fw-bold text-dark mb-2'>{$title}</h4>
                <p class='text-muted small mb-4'>{$message}</p>
                <div class='d-flex gap-2 justify-content-center'>
                    <button onclick='window.history.back()' class='btn btn-outline-secondary rounded-3 px-4'>
                        <i class='fa-solid fa-arrow-left me-1'></i> Go Back
                    </button>
                    <a href='/employees' class='btn btn-primary rounded-3 px-4'>
                        <i class='fa-solid fa-users me-1'></i> Employees
                    </a>
                </div>
            </div>
        </body>
        </html>
        ";

        return response($html, 200)->header('Content-Type', 'text/html');
    }
}
