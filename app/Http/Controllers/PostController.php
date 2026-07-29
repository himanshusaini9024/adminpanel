<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTag;
use App\Models\User;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $posts=Post::getAllPost();
        // return $posts;
        return view('backend.post.index')->with('posts',$posts);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories=PostCategory::get();
        $tags=PostTag::get();
        $users=User::get();
        return view('backend.post.create')->with('users',$users)->with('categories',$categories)->with('tags',$tags);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'quote' => 'nullable|string|max:500',
            'summary' => 'required|string|max:1000',
            'description' => 'nullable|string',
            'photo' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'added_by' => 'nullable|exists:users,id',
            'post_cat_id' => 'required|exists:post_categories,id',
            'status' => 'required|in:active,inactive'
        ]);

        try {
            $slug = generateUniqueSlug($request->title, Post::class);
            $validated['slug'] = $slug;
            $validated['added_by'] = $validated['added_by'] ?? auth()->id();

            if (!empty($validated['photo'])) {
                $validated['photo'] = media_path($validated['photo']);
            }

            if ($request->filled('tags')) {
                $validated['tags'] = implode(',', $request->input('tags'));
            } else {
                $validated['tags'] = '';
            }

            $post = Post::create($validated);
            
            return redirect()->route('post.index')
                ->with('success', 'Post successfully added');
        } catch (\Exception $e) {
            \Log::error('Post creation failed: ' . $e->getMessage());
            return redirect()->route('post.index')
                ->with('error', 'Please try again!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $post=Post::findOrFail($id);
        $categories=PostCategory::get();
        $tags=PostTag::get();
        $users=User::get();
        return view('backend.post.edit')->with('categories',$categories)->with('users',$users)->with('tags',$tags)->with('post',$post);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'quote' => 'nullable|string|max:500',
            'summary' => 'required|string|max:1000',
            'description' => 'nullable|string',
            'photo' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'added_by' => 'nullable|exists:users,id',
            'post_cat_id' => 'required|exists:post_categories,id',
            'status' => 'required|in:active,inactive'
        ]);

        try {
            if (!empty($validated['photo'])) {
                $validated['photo'] = media_path($validated['photo']);
            }

            if ($request->filled('tags')) {
                $validated['tags'] = implode(',', $request->input('tags'));
            } else {
                $validated['tags'] = '';
            }

            $status = $post->update($validated);
            
            return redirect()->route('post.index')
                ->with($status ? 'success' : 'error',
                    $status ? 'Post successfully updated' : 'Please try again!');
        } catch (\Exception $e) {
            \Log::error('Post update failed: ' . $e->getMessage());
            return redirect()->route('post.index')
                ->with('error', 'Please try again!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $post = Post::findOrFail($id);
            $status = $post->delete();
            
            return redirect()->route('post.index')
                ->with($status ? 'success' : 'error',
                    $status ? 'Post successfully deleted' : 'Error while deleting post');
        } catch (\Exception $e) {
            \Log::error('Post deletion failed: ' . $e->getMessage());
            return redirect()->route('post.index')
                ->with('error', 'Error while deleting post');
        }
    }
}
