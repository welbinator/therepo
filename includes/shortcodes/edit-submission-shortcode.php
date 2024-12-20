<?php

namespace TheRepo\Shortcode\Edit;

function repo_edit_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to edit your submissions. <a href="' . wp_login_url(get_permalink()) . '">Log in</a> or <a href="' . wp_registration_url() . '">Register</a>.</p>';
    }

    $current_user_id = get_current_user_id();
    $user_submissions = get_posts([
        'post_type' => ['plugin', 'theme_repo'],
        'author' => $current_user_id,
        'post_status' => ['publish', 'pending'],
        'posts_per_page' => -1,
    ]);

    if (empty($user_submissions)) {
        return '<p>You don\'t have any submissions to edit.</p>';
    }

    ob_start(); ?>
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-2xl mx-auto">
                <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="space-y-6" id="repo_edit_form">
                    <input type="hidden" name="action" value="repo_edit_submission">
                    <?php wp_nonce_field('repo_edit_submission', 'repo_nonce'); ?>

                    <!-- Select Submission -->
                    <div>
                        <label for="submission_id" class="block text-sm font-medium text-gray-700">Select a Submission to Edit</label>
                        <select name="submission_id" id="submission_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Select --</option>
                            <?php foreach ($user_submissions as $submission) : ?>
                                <option value="<?php echo esc_attr($submission->ID); ?>"><?php echo esc_html($submission->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fields to Edit -->
                    <div id="edit-fields" style="display: none;">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="name" id="name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <!-- Hosted on GitHub? -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Hosted on GitHub?</label>
                            <div class="mt-1">
                                <label>
                                    <input type="radio" name="hosted_on_github" value="yes" checked> Yes
                                </label>
                                <label class="ml-4">
                                    <input type="radio" name="hosted_on_github" value="no"> No
                                </label>
                            </div>
                        </div>
                       
                        <!-- Download URL Field Wrapper -->
                        <div id="download_url_field" style="display: none;">
                            <label for="download_url" class="block text-sm font-medium text-gray-700">Download URL</label>
                            <input type="url" name="download_url" id="download_url" class="mt-1 block w-full px-3 py-2 border rounded-md">
                        </div>


                        <!-- GitHub Fields Wrapper -->
                        <div id="github_fields">
                            <div>
                                <label for="github_username" class="block text-sm font-medium text-gray-700">GitHub Username</label>
                                <input type="text" name="github_username" id="github_username" class="mt-1 block w-full px-3 py-2 border rounded-md">
                            </div>
                            <div>
                                <label for="github_repo" class="block text-sm font-medium text-gray-700">GitHub Repo</label>
                                <input type="text" name="github_repo" id="github_repo" class="mt-1 block w-full px-3 py-2 border rounded-md">
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" id="description" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>

                        <div>
                            <label for="categories" class="block text-sm font-medium text-gray-700">Categories</label>
                            <input type="text" name="categories" id="categories" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        
                        <div class="flex items-center">
                            <div><label for="featured_image" class="block text-sm font-medium text-gray-700">Featured Image</label>
                            <input type="file" name="featured_image" id="featured_image" class="mt-1 block w-full text-sm text-gray-500 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div id="featured-image-preview" class="mb-4"></div><br />
                        </div>
                        

                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Update Submission
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
    <script>
    // Function to toggle GitHub and Download URL fields based on "Hosted on GitHub?"
    function toggleFields(hostedOnGitHub) {
        const githubFields = document.getElementById('github_fields'); // Wrapper for GitHub fields
        const downloadUrlField = document.getElementById('download_url_field'); // Download URL field wrapper

        if (hostedOnGitHub === 'yes') {
            // Show GitHub fields, hide Download URL field
            githubFields.style.display = 'block';
            downloadUrlField.style.display = 'none';
            document.getElementById('github_username').setAttribute('required', 'required');
            document.getElementById('github_repo').setAttribute('required', 'required');
            document.getElementById('download_url').removeAttribute('required');
        } else {
            // Hide GitHub fields, show Download URL field
            githubFields.style.display = 'none';
            downloadUrlField.style.display = 'block';
            document.getElementById('github_username').removeAttribute('required');
            document.getElementById('github_repo').removeAttribute('required');
            document.getElementById('download_url').setAttribute('required', 'required');
        }
    }

    // Event listener for submission dropdown change
    document.getElementById('submission_id').addEventListener('change', function () {
        const submissionId = this.value;

        if (!submissionId) {
            // Hide edit fields if no submission is selected
            document.getElementById('edit-fields').style.display = 'none';
            return;
        }

        // Show edit fields when a submission is selected
        document.getElementById('edit-fields').style.display = 'block';

        // Fetch submission data via AJAX
        fetch(`<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=get_submission_data&id=${submissionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const submission = data.data;

                    // Populate form fields
                    document.getElementById('name').value = submission.name || '';
                    document.getElementById('github_username').value = submission.github_username || '';
                    document.getElementById('github_repo').value = submission.github_repo || '';
                    document.getElementById('description').value = submission.description || '';
                    document.getElementById('categories').value = submission.categories || '';
                    document.getElementById('download_url').value = submission.download_url || '';

                    // Set "Hosted on GitHub?" value
                    const hostedOnGitHub = submission.hosted_on_github || 'yes';
                    document.querySelector(`input[name="hosted_on_github"][value="${hostedOnGitHub}"]`).checked = true;

                    // Toggle the appropriate fields based on the hostedOnGitHub value
                    toggleFields(hostedOnGitHub);

                    console.log('Fields populated and toggled based on submission data.');
                } else {
                    console.error('Error fetching submission data:', data.data);
                    alert('Failed to load submission data. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error during AJAX request:', error);
                alert('An error occurred while fetching submission data. Check the console for details.');
            });
    });

    // Initialize event listeners on page load
    document.addEventListener('DOMContentLoaded', function () {
        // Attach event listeners to Hosted on GitHub radio buttons
        document.querySelectorAll('input[name="hosted_on_github"]').forEach(radio => {
            radio.addEventListener('change', function () {
                toggleFields(this.value);
            });
        });

        // Ensure proper display of fields when the page loads
        const initialSelection = document.querySelector('input[name="hosted_on_github"]:checked').value;
        toggleFields(initialSelection);

        // Ensure #edit-fields is visible if a submission is pre-selected
        const submissionId = document.getElementById('submission_id').value;
        if (submissionId) {
            document.getElementById('edit-fields').style.display = 'block';
        }
    });
</script>





    <?php
    return ob_get_clean();
}

add_shortcode('repo_edit_form', __NAMESPACE__ . '\\repo_edit_form_shortcode');

// Form submission handler
add_action('admin_post_repo_edit_submission', __NAMESPACE__ . '\\handle_repo_edit_submission');

function handle_repo_edit_submission() {
    if (!isset($_POST['repo_nonce']) || !wp_verify_nonce($_POST['repo_nonce'], 'repo_edit_submission')) {
        wp_die('Error: Invalid form submission.');
    }

    if (!is_user_logged_in()) {
        wp_die('Error: You must be logged in to edit a submission.');
    }

    $submission_id = absint($_POST['submission_id']);
    $post = get_post($submission_id);

    // Ensure the post exists and belongs to the current user
    if (!$post || $post->post_author != get_current_user_id()) {
        wp_die('Error: Unauthorized access to this submission.');
    }

    $name = sanitize_text_field($_POST['name']);
    $github_username = sanitize_text_field($_POST['github_username']);
    $github_repo = sanitize_text_field($_POST['github_repo']);
    $description = sanitize_textarea_field($_POST['description']);
    $categories = sanitize_text_field($_POST['categories']);
    $download_url = esc_url_raw($_POST['download_url']);


    if (empty($name) || empty($github_username) || empty($github_repo) || empty($description)) {
        wp_die('Error: All fields are required.');
    }

    // Update the post title and content
    wp_update_post([
        'ID' => $submission_id,
        'post_title' => $name,
        'post_content' => $description,
    ]);

    // Update GitHub meta fields
    update_post_meta($submission_id, 'github_username', $github_username);
    update_post_meta($submission_id, 'github_repo', $github_repo);
    update_post_meta($submission_id, 'download_url', $download_url);


    // Update categories
    $post_type = $post->post_type;
    $taxonomy = $post_type === 'plugin' ? 'plugin-category' : 'theme-category';
    $category_list = array_map('trim', explode(',', $categories));
    wp_set_object_terms($submission_id, $category_list, $taxonomy);

    // Handle featured image upload
    if (!empty($_FILES['featured_image']['name'])) {
        $upload = wp_handle_upload($_FILES['featured_image'], array('test_form' => false));

        if ($upload && !isset($upload['error'])) {
            $filetype = wp_check_filetype($upload['file']);
            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name($_FILES['featured_image']['name']),
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            $featured_image_id = wp_insert_attachment($attachment, $upload['file']);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($featured_image_id, $upload['file']);
            wp_update_attachment_metadata($featured_image_id, $attach_data);

            // Set as the post's featured image
            set_post_thumbnail($submission_id, $featured_image_id);
        } else {
            wp_die('Error uploading featured image: ' . $upload['error']);
        }
    }

    // Redirect back to the edit form with a success message
    wp_redirect(add_query_arg('updated', 'true', get_permalink($submission_id)));
    exit;
}