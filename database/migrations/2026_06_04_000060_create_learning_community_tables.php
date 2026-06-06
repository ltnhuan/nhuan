<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_forums', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('forum_type');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('visibility')->default('course');
            $table->string('status')->default('active');
            $table->boolean('is_pinned')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('thread_count')->default(0);
            $table->unsignedInteger('post_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'forum_type', 'status']);
            $table->index(['course_id', 'class_id', 'faculty_id']);
        });

        Schema::create('discussion_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('forum_id');
            $table->string('thread_type')->default('discussion');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->string('status')->default('open');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('last_post_by')->nullable();
            $table->unsignedInteger('reply_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('upvote_count')->default(0);
            $table->unsignedBigInteger('correct_post_id')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'forum_id', 'thread_type', 'status']);
            $table->index(['is_pinned', 'last_activity_at']);
        });

        Schema::create('discussion_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('quoted_post_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('post_type')->default('reply');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->string('status')->default('visible');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_correct_answer')->default(false);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('upvote_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'thread_id', 'status']);
            $table->index(['parent_id', 'quoted_post_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('discussion_mentions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('mentioned_user_id');
            $table->unsignedBigInteger('mentioned_by');
            $table->timestamp('created_at')->nullable();
            $table->unique(['post_id', 'mentioned_user_id']);
            $table->index(['tenant_id', 'mentioned_user_id']);
        });

        Schema::create('discussion_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('user_id');
            $table->string('reaction_type')->default('like');
            $table->timestamp('created_at')->nullable();
            $table->unique(['post_id', 'user_id', 'reaction_type']);
            $table->index(['tenant_id', 'thread_id', 'reaction_type']);
        });

        Schema::create('wiki_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('wiki_type');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('slug');
            $table->string('title');
            $table->text('body_html')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('published');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'wiki_type', 'slug']);
            $table->index(['tenant_id', 'wiki_type', 'status']);
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('blog_type');
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->text('body_html');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'blog_type', 'status']);
        });

        Schema::create('community_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('group_type');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('visibility')->default('members');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedInteger('member_count')->default(0);
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'group_type', 'status']);
            $table->index(['course_id', 'owner_id']);
        });

        Schema::create('community_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->unique(['group_id', 'user_id']);
            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('reputation_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('points')->default(0);
            $table->string('rank')->default('new_member');
            $table->unsignedInteger('badge_count')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'points']);
        });

        Schema::create('reputation_badges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('required_points')->default(0);
            $table->jsonb('criteria')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('badge_id');
            $table->timestamp('awarded_at')->nullable();
            $table->unique(['user_id', 'badge_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('reputation_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('points');
            $table->string('event_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'user_id', 'event_type']);
        });

        Schema::create('moderation_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            $table->unsignedBigInteger('reported_by');
            $table->string('reason');
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['reportable_type', 'reportable_id']);
        });

        Schema::create('community_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('notification_type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_url')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'read_at']);
        });

        Schema::create('community_activity_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type');
            $table->unsignedBigInteger('forum_id')->nullable();
            $table->unsignedBigInteger('thread_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'event_type', 'created_at']);
            $table->index(['forum_id', 'thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_activity_events');
        Schema::dropIfExists('community_notifications');
        Schema::dropIfExists('moderation_reports');
        Schema::dropIfExists('reputation_events');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('reputation_badges');
        Schema::dropIfExists('reputation_profiles');
        Schema::dropIfExists('community_group_members');
        Schema::dropIfExists('community_groups');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('wiki_pages');
        Schema::dropIfExists('discussion_reactions');
        Schema::dropIfExists('discussion_mentions');
        Schema::dropIfExists('discussion_posts');
        Schema::dropIfExists('discussion_threads');
        Schema::dropIfExists('community_forums');
    }
};
