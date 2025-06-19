<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // For companies to view applications to their job postings
        $companyId = Auth::id();
        $jobPostings = JobPosting::where('company_id', $companyId)->pluck('id');
        $applications = Application::whereIn('job_posting_id', $jobPostings)->with(['jobPosting', 'student'])->get();

        return response()->json($applications);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'job_posting_id' => 'required|exists:job_postings,id',
            'resume' => 'required|file|mimes:pdf|max:10240',
            'cover_letter' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $jobPosting = JobPosting::findOrFail($request->job_posting_id);

        // Ensure the student is authenticated
        $studentId = Auth::id();

        // Handle resume upload
        $resumePath = $request->file('resume')->store('resumes', 'public');

        // Handle cover letter upload (if provided)
        $coverLetterPath = null;
        if ($request->hasFile('cover_letter')) {
            $coverLetterPath = $request->file('cover_letter')->store('cover_letters', 'public');
        }

        $application = Application::create([
            'job_posting_id' => $jobPosting->id,
            'student_id' => $studentId,
            'status' => 'pending',
            'resume_path' => $resumePath,
            'cover_letter_path' => $coverLetterPath,
        ]);

        return response()->json($application, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $application = Application::with(['jobPosting', 'student'])->findOrFail($id);

        // Authorize: Only the student who applied or the company owning the job posting can view
        if ($application->student_id !== Auth::id() && $application->jobPosting->company_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($application);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $application = Application::findOrFail($id);

        // Authorize: Only the company owning the job posting can update the application status
        if ($application->jobPosting->company_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|string|in:pending,reviewed,accepted,rejected',
        ]);

        $application->update($request->only('status'));

        return response()->json($application);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $application = Application::findOrFail($id);

        // Authorize: Only the student who applied or the company owning the job posting can delete
        if ($application->student_id !== Auth::id() && $application->jobPosting->company_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated files
        Storage::disk('public')->delete($application->resume_path);
        if ($application->cover_letter_path) {
            Storage::disk('public')->delete($application->cover_letter_path);
        }

        $application->delete();

        return response()->json(null, 204);
    }
}
