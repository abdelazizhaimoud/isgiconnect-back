<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecruitmentAnalyticsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $companyId = Auth::id();

        // Get job postings for the current company
        $jobPostings = JobPosting::where('company_id', $companyId)->get();
        $jobPostingIds = $jobPostings->pluck('id');

        // Total job postings
        $totalJobPostings = $jobPostings->count();

        // Total applications for company's job postings
        $totalApplications = Application::whereIn('job_posting_id', $jobPostingIds)->count();

        // Applications by status
        $applicationsByStatus = Application::whereIn('job_posting_id', $jobPostingIds)
                                            ->select('status', DB::raw('count(*) as count'))
                                            ->groupBy('status')
                                            ->get()
                                            ->keyBy('status')
                                            ->map->count;

        // Applications per job posting
        $applicationsPerJobPosting = Application::whereIn('job_posting_id', $jobPostingIds)
                                                ->select('job_posting_id', DB::raw('count(*) as count'))
                                                ->groupBy('job_posting_id')
                                                ->with('jobPosting:id,title') // Eager load job posting title
                                                ->get();

        return response()->json([
            'total_job_postings' => $totalJobPostings,
            'total_applications' => $totalApplications,
            'applications_by_status' => $applicationsByStatus,
            'applications_per_job_posting' => $applicationsPerJobPosting,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // This controller is for analytics, so store method is not applicable
        return response()->json(['message' => 'Method not allowed'], 405);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Not applicable for aggregate analytics
        return response()->json(['message' => 'Method not allowed'], 405);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Not applicable for aggregate analytics
        return response()->json(['message' => 'Method not allowed'], 405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Not applicable for aggregate analytics
        return response()->json(['message' => 'Method not allowed'], 405);
    }
}
