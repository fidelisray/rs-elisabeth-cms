<?php

namespace App\Support;

/** Kode dan skala sama dengan StoreQuestionnaireRequest pada Web Frontend. */
final class QuestionnaireDefinition
{
    public const SCALES = [
        'puas' => ['Sangat Tidak Puas', 'Tidak Puas', 'Puas', 'Sangat Puas'],
        'setuju' => ['Sangat Tidak Setuju', 'Tidak Setuju', 'Setuju', 'Sangat Setuju'],
        'mudah' => ['Sangat Tidak Mudah', 'Tidak Mudah', 'Mudah', 'Sangat Mudah'],
        'memadai' => ['Sangat Tidak Memadai', 'Tidak Memadai', 'Memadai', 'Sangat Memadai'],
    ];

    public const QUESTIONS = [
        'igd' => [
            'speed_triage_arrival' => 'puas',
            'triage_efficiency' => 'puas',
            'post_triage_care_speed' => 'puas',
            'waiting_time_acceptable' => 'setuju',
            'medical_explanation' => 'puas',
            'informed_procedure_risks' => 'setuju',
            'staff_responsiveness' => 'setuju',
            'calm_communication' => 'puas',
            'emotional_support' => 'puas',
            'family_support' => 'setuju',
            'waiting_area_comfort' => 'puas',
            'family_information_clarity' => 'puas',
            'overall_igd_experience' => 'puas',
            'information_accessibility' => 'mudah',
            'recommend_igd' => 'setuju',
        ],
        'rawat-jalan' => [
            'waiting_room_comfort' => 'puas',
            'examination_room_comfort' => 'puas',
            'parking_staff' => 'puas',
            'doctor_schedule_punctuality' => 'puas',
            'doctor_explanation' => 'puas',
            'nurse_responsiveness' => 'puas',
            'staff_information_clarity' => 'puas',
            'staff_friendliness' => 'puas',
            'staff_problem_solving' => 'puas',
            'overall_outpatient_experience' => 'puas',
            'recommend_hospital' => 'setuju',
        ],
        'rawat-inap' => [
            'room_bed_comfort' => 'puas',
            'room_cleanliness' => 'puas',
            'room_facilities' => 'puas',
            'environment_peace' => 'puas',
            'doctor_visit_frequency' => 'puas',
            'doctor_explanation' => 'puas',
            'nurse_responsiveness' => 'puas',
            'nurse_care_quality' => 'puas',
            'medical_staff_info' => 'puas',
            'decision_involvement' => 'puas',
            'procedure_adequacy' => 'memadai',
            'family_communication' => 'puas',
            'food_quality' => 'puas',
            'diet_menu_suitability' => 'setuju',
            'diet_explanation' => 'puas',
            'overall_food_service' => 'puas',
            'overall_inpatient_experience' => 'puas',
            'information_ease' => 'mudah',
            'recommend_inpatient' => 'setuju',
        ],
    ];

    /** Label operasional untuk pembacaan jawaban; kode lama tetap bisa ditampilkan. */
    public static function label(string $code): string
    {
        return [
            'speed_triage_arrival' => 'Kecepatan penanganan saat tiba',
            'triage_efficiency' => 'Efisiensi triase',
            'post_triage_care_speed' => 'Kecepatan perawatan setelah triase',
            'waiting_time_acceptable' => 'Kewajaran waktu tunggu',
            'medical_explanation' => 'Penjelasan medis',
            'informed_procedure_risks' => 'Informasi prosedur dan risiko',
            'staff_responsiveness' => 'Respons petugas',
            'calm_communication' => 'Komunikasi yang menenangkan',
            'emotional_support' => 'Dukungan emosional',
            'family_support' => 'Dukungan kepada keluarga',
            'waiting_area_comfort' => 'Kenyamanan ruang tunggu',
            'family_information_clarity' => 'Kejelasan informasi kepada keluarga',
            'overall_igd_experience' => 'Pengalaman IGD keseluruhan',
            'information_accessibility' => 'Akses informasi',
            'recommend_igd' => 'Rekomendasi IGD',
            'waiting_room_comfort' => 'Kenyamanan ruang tunggu',
            'examination_room_comfort' => 'Kenyamanan ruang pemeriksaan',
            'parking_staff' => 'Pelayanan petugas parkir',
            'doctor_schedule_punctuality' => 'Ketepatan jadwal dokter',
            'doctor_explanation' => 'Penjelasan dokter',
            'nurse_responsiveness' => 'Respons perawat',
            'staff_information_clarity' => 'Kejelasan informasi petugas',
            'staff_friendliness' => 'Keramahan petugas',
            'staff_problem_solving' => 'Penyelesaian masalah oleh petugas',
            'overall_outpatient_experience' => 'Pengalaman rawat jalan keseluruhan',
            'recommend_hospital' => 'Rekomendasi rumah sakit',
            'room_bed_comfort' => 'Kenyamanan kamar dan tempat tidur',
            'room_cleanliness' => 'Kebersihan kamar',
            'room_facilities' => 'Fasilitas kamar',
            'environment_peace' => 'Ketenangan lingkungan',
            'doctor_visit_frequency' => 'Frekuensi kunjungan dokter',
            'nurse_care_quality' => 'Kualitas asuhan perawat',
            'medical_staff_info' => 'Informasi tenaga medis',
            'decision_involvement' => 'Keterlibatan dalam keputusan',
            'procedure_adequacy' => 'Kecukupan prosedur',
            'family_communication' => 'Komunikasi dengan keluarga',
            'food_quality' => 'Kualitas makanan',
            'diet_menu_suitability' => 'Kesesuaian menu diet',
            'diet_explanation' => 'Penjelasan diet',
            'overall_food_service' => 'Pelayanan makanan keseluruhan',
            'overall_inpatient_experience' => 'Pengalaman rawat inap keseluruhan',
            'information_ease' => 'Kemudahan informasi',
            'recommend_inpatient' => 'Rekomendasi rawat inap',
        ][$code] ?? $code;
    }
}
