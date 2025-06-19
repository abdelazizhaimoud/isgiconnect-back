<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JobPostingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function companyIndex()
    {
        // Return all job postings for the authenticated company's dashboard
        $jobPostings = \App\Models\JobPosting::where('company_id', \Illuminate\Support\Facades\Auth::id())->latest()->get();
        return response()->json($jobPostings);
    }

    public function index()
    {
        $user = Auth::guard('sanctum')->user();
        $studentId = $user ? $user->id : null;

        // Eager load company information and get active job postings
        $jobPostings = JobPosting::where('status', 'active')->with('company')->latest()->get();

        if ($studentId) {
            // Get all application statuses for the current student for the displayed jobs
            $applications = \App\Models\Application::where('student_id', $studentId)
                ->whereIn('job_posting_id', $jobPostings->pluck('id'))
                ->pluck('status', 'job_posting_id');

            // Attach the application status to each job posting
            $jobPostings->each(function ($jobPosting) use ($applications) {
                $jobPosting->application_status = $applications->get($jobPosting->id);
            });
        } else {
            // If no user is logged in, there's no application status
            $jobPostings->each(function ($jobPosting) {
                $jobPosting->application_status = null;
            });
        }

        return response()->json($jobPostings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'requirements' => 'required|string',
            'location' => 'required|string|max:255',
            'type' => 'required|string|in:internship,full-time,part-time',
            'application_deadline' => 'required|date',
        ]);

        $jobPosting = JobPosting::create([
            'company_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'requirements' => $request->requirements,
            'location' => $request->location,
            'type' => $request->type,
            'application_deadline' => $request->application_deadline,
            'status' => $request->status ?? 'active',
        ]);

        return response()->json($jobPosting, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Eager load company information
        $jobPosting = JobPosting::with('company')->findOrFail($id);

        $user = Auth::guard('sanctum')->user();
        $studentId = $user ? $user->id : null;

        if ($studentId) {
            // Find the application for the current student and job
            $application = \App\Models\Application::where('student_id', $studentId)
                ->where('job_posting_id', $jobPosting->id)
                ->first();
            // Attach the application status to the job posting
            $jobPosting->application_status = $application ? $application->status : null;
        } else {
            // If no user is logged in, there's no application status
            $jobPosting->application_status = null;
        }

        return response()->json($jobPosting);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $jobPosting = JobPosting::where('company_id', Auth::id())->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'requirements' => 'sometimes|required|string',
            'location' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:internship,full-time,part-time',
            'application_deadline' => 'sometimes|required|date',
            'status' => 'sometimes|required|string|in:active,closed,draft',
        ]);

        $jobPosting->update($request->all());

        return response()->json($jobPosting);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $jobPosting = JobPosting::where('company_id', Auth::id())->findOrFail($id);
        $jobPosting->delete();

        return response()->json(null, 204);
    }
}
