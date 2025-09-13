<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class Social_media_Timewaste extends Controller
{
    protected function success(string $message, array $data = [], int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'error'   => null,
            'data'    => $data,
        ], $code);
    }

    protected function error(string $message, array $errors = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'error'   => $errors,
            'data'    => null,
        ], $code);
    }

    /**
     * Social Media Time-Waste Calculator
     *
     * Expected JSON:
     * - daily_social_media_minutes (numeric)  e.g. 120
     * - daily_sleep_hours (numeric)          e.g. 8
     * - daily_chores_hours (numeric)         e.g. 2
     * - current_age_years (int)              e.g. 25
     * - expected_lifespan_years (int)        e.g. 80
     * - hourly_earning_potential (numeric)   e.g. 5000
     */
    public function calculate(Request $request): JsonResponse
    {
        try {
            $rules = [
                'daily_social_media_minutes' => 'required|numeric|min:0',
                'daily_sleep_hours' => 'required|numeric|min:0|max:24',
                'daily_chores_hours' => 'required|numeric|min:0|max:24',
                'current_age_years' => 'required|numeric|min:0|max:150',
                'expected_lifespan_years' => 'required|numeric|min:1|max:300',
                'hourly_earning_potential' => 'required|numeric|min:0',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
            }

            // Inputs
            $dailySocialMinutes = (float) $request->input('daily_social_media_minutes');
            $dailySleepHours = (float) $request->input('daily_sleep_hours');
            $dailyChoresHours = (float) $request->input('daily_chores_hours');
            $currentAge = (int) $request->input('current_age_years');
            $expectedLifespan = (int) $request->input('expected_lifespan_years');
            $hourlyEarning = (float) $request->input('hourly_earning_potential');

            // Lifetime years remaining
            $remainingYears = max(0, $expectedLifespan - $currentAge);

            // Basic conversions
            $dailySocialHours = $dailySocialMinutes / 60.0;                       // hours per day
            $annualSocialHours = $dailySocialHours * 365.0;                       // hours per year
            $lifetimeSocialHours = $annualSocialHours * $remainingYears;         // total hours in remaining life
            $lifetimeSocialDays = $lifetimeSocialHours / 24.0;
            $lifetimeSocialYears = $lifetimeSocialDays / 365.0;

            // Opportunity cost = lifetime social hours * hourly earning
            $opportunityCost = $lifetimeSocialHours * $hourlyEarning;

            // Sleeping
            $annualSleepHours = $dailySleepHours * 365.0;
            $lifetimeSleepHours = $annualSleepHours * $remainingYears;
            $lifetimeSleepYears = $lifetimeSleepHours / (24.0 * 365.0); // same as /8760

            // Chores
            $annualChoresHours = $dailyChoresHours * 365.0;
            $lifetimeChoresHours = $annualChoresHours * $remainingYears;
            $lifetimeChoresYears = $lifetimeChoresHours / (24.0 * 365.0);

            // Leftover Time — (matching your screenshot logic)
            // NOTE: Your screenshot shows "Leftover Time (hrs)" equal to the SUM of (social + sleep + chores).
            // To match exactly we will follow the same. If you want "remaining time" (i.e. total hours minus these),
            // change the calculation accordingly.
            $leftoverHours = $lifetimeSocialHours + $lifetimeSleepHours + $lifetimeChoresHours;
            $leftoverDays = $leftoverHours / 24.0;
            $leftoverYears = $leftoverDays / 365.0;

            // Books/Skills/Marathons from lifetime social hours
            $booksCouldHaveRead = $lifetimeSocialHours / 20.0;    // ~20 hrs/book
            $skillsCouldHaveLearned = $lifetimeSocialHours / 50.0; // ~50 hrs/skill
            $marathonsTrained = $lifetimeSocialHours / 150.0;     // ~150 hrs/marathon

            // Format outputs to match screenshot style (decimals)
            $out = [
                'daily_social_media_usage_hours' => round($dailySocialHours, 2),          // 2.00
                'annual_social_media_usage_hours' => round($annualSocialHours, 1),        // 730.0
                'lifetime_social_media_hours' => round($lifetimeSocialHours, 1),         // 40150.0
                'lifetime_social_media_days' => round($lifetimeSocialDays, 1),           // 1672.9
                'lifetime_social_media_years' => round($lifetimeSocialYears, 2),         // 4.58

                'opportunity_cost' => round($opportunityCost, 2),                        // numeric
                'opportunity_cost_display' => $this->formatIndianCurrency(round($opportunityCost, 0)), // formatted

                'lifetime_sleeping_hours' => round($lifetimeSleepHours, 1),             // 160600.0
                'lifetime_sleeping_years' => round($lifetimeSleepYears, 2),             // 18.33

                'lifetime_chores_hours' => round($lifetimeChoresHours, 1),              // 40150.0
                'lifetime_chores_years' => round($lifetimeChoresYears, 2),              // 4.58

                'leftover_time_hours' => round($leftoverHours, 1),                      // 240900.0 (matches screenshot logic)
                'leftover_time_days' => round($leftoverDays, 1),                        // 10037.5
                'leftover_time_years' => round($leftoverYears, 2),                      // 27.50

                'books_could_have_read' => (int) round($booksCouldHaveRead),            // 2008
                'skills_could_have_learned' => (int) round($skillsCouldHaveLearned),    // 803
                'marathons_trained' => (int) round($marathonsTrained),                  // 268
            ];

            return $this->success('Social media time-waste calculation completed.', $out, 200);
        } catch (\Throwable $ex) {
            Log::error('Exception in Lic_social_time_waste_controller::calculate', [
                'message' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error while calculating social time-waste. See logs for details.',
                'error' => ['exception' => $ex->getMessage()],
                'data' => null
            ], 500);
        }
    }

    /**
     * Format number to Indian currency grouping (e.g., 200750000 -> 20,07,50,000)
     * Does not add currency symbol (you can prefix it in front in UI).
     */
    private function formatIndianCurrency($num): string
    {
        $num = (string) round($num, 0);
        $len = strlen($num);
        if ($len <= 3) return $num;
        $last3 = substr($num, -3);
        $rest = substr($num, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        return $rest . ',' . $last3;
    }
}
