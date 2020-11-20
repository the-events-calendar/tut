<?php
namespace TUT\GitHub;

class API {
	protected $client;
	protected $org;

	private $oauth_token;
	public $user;
	public $debug = false;

	/**
	 * Setup GitHub API
	 *
	 * @param string $org the organization to run against
	 */
	public function __construct( $org ) {
		$this->org = $org;

		$config_loader = new Config_Loader;
		$config  = $config_loader->get_script_config();

		// go get the oauth token
		$this->oauth_token = $config['github']['oauth_token'];

		// this should be the user you want generating your pull requests
		// it is advisable to create an account solely for this purpose
		$this->user = $config['github']['user'];
	}//end __construct

	public static function factory( $org ) {
		static $instances;

		if ( isset( $instances[ $org ] ) ) {
			return $instances[ $org ];
		}

		return $instances[ $org ] = new static( $org );
	}

	/**
	 * Gets the latest branch commit for the given branch
	 *
	 * @param $repo
	 * @param $branch
	 * @return array|object
	 */
	public function get_latest_branch_commit( $repo, $branch ) {
		$url  = "{$this->api_base}/repos/{$this->org}/{$repo}/git/refs/heads/{$branch}";
		$ref  = $this->get( $url, [] );
		return $this->get( $ref->object->url, [] );
	}

	/**
	 * Gets a list of branches
	 *
	 * @param $repo
	 *
	 * @return array
	 */
	public function branches( $repo ) {
		$url = "{$this->api_base}/repos/{$this->org}/{$repo}/branches";
		return $this->get( $url );
	}

	/**
	 * Gets contents of a file from the repo
	 *
	 * @param $repo
	 * @param $branch
	 * @return array|object
	 */
	public function get_file( $repo, $ref, $path ) {
		$url  = "{$this->api_base}/repos/{$this->org}/{$repo}/contents/{$path}";
		return $this->get( $url, [ 'ref' => $ref ] );
	}

	/**
	 * get repositories
	 * see also: https://developer.github.com/v3/repos/#list-organization-repositories
	 *
	 * @param array $args (optional) any additional parameters to add to the GET in the API request
	 * @return array containing repository objects
	 */
	public function repos( $args = [] ) {
		$args['per_page'] = isset( $args['per_page'] ) ? $args['per_page'] : 100;

		$url = "{$this->api_base}/orgs/{$this->org}/repos";
		return $this->get( $url, $args );
	}// end repos

	/**
	 * get all issues
	 * see also: https://developer.github.com/v3/issues/#list-issues-for-a-repository
	 *
	 * @param string $repo the name of the repository
	 * @param array $args (optional) any additional parameters to add to the GET in the API request
	 */
	public function issues( $repo, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/issues";
		return $this->get( $url, $args );
	}//end issues

	public function teams() {
		$url = "{$this->api_base}/orgs/{$this->org}/teams";
		return $this->get( $url );
	}

	public function team_members( $team_id, $args = [] ) {
		$url = "{$this->api_base}/teams/{$team_id}/members";
		return $this->get( $url, $args );
	}

	/**
	 * create an issue
	 * see also: https://developer.github.com/v3/issues/#create-an-issue
	 *
	 * @param string $repo the name of the repository
	 * @param string $title title of the issue
	 * @param  $args (optional) any additional parameters to add to the POST in the API request
	 */
	public function create_issue( $repo, $title, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/issues";

		$args['title'] = $title;

		return $this->post( $url, $args );
	}// end create_issue

	/**
	 * edit an issue
	 * see also: https://developer.github.com/v3/issues/#edit-an-issue
	 *
	 * @param string $repo the name of the repository
	 * @param object $issue
	 */
	public function edit_issue( $repo, $issue ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/issues/{$issue->number}";

		return $this->patch( $url, $issue );
	}// end edit_issue

	/**
	 * get all pull requests
	 * see also: https://developer.github.com/v3/pulls/#list-pull-requests
	 *
	 * @param string $repo the name of the repository
	 * @param array $args (optional) any additional parameters to add to the GET in the API request
	 */
	public function pull_requests( $repo, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls";
		return $this->get( $url, $args, true );
	}//end pull_requests

	/**
	 * get a single pull request
	 * see also: https://developer.github.com/v3/pulls/#get-a-single-pull-request
	 *
	 * @param string $repo the name of the repository
	 * @param int $pull_number The PR number
	 * @param array $args (optional) any additional parameters to add to the GET in the API request
	 */
	public function pull_request( $repo, $pull_number, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/{$pull_number}";
		return $this->get( $url, $args, true );
	}

	/**
	 * get a pull request's diff
	 * see also: https://developer.github.com/v3/pulls/#get-a-single-pull-request
	 *
	 * @param string $repo the name of the repository
	 * @param int $pull_number The PR number
	 * @param array $args (optional) any additional parameters to add to the GET in the API request
	 */
	public function pull_request_diff( $repo, $pull_number, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/{$pull_number}";
		return $this->get_diff( $url, $args );
	}

	public function milestones( $repo, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/milestones";
		return $this->get( $url, $args, true );
	}

	public function create_milestone( $repo, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/milestones";
		return $this->post( $url, $args, true );
	}

	public function projects( $repo, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/projects";
		return $this->get( $url, $args, 'application/vnd.github.inertia-preview+json' );
	}

	public function create_project( $repo, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/projects";
		return $this->post( $url, $args, 'application/vnd.github.inertia-preview+json' );
	}

	public function project_columns( $project_id, $args = [] ) {
		$url = "{$this->api_base}/projects/{$project_id}/columns";
		return $this->get( $url, $args, 'application/vnd.github.inertia-preview+json' );
	}

	public function create_project_column( $project_id, $args = [] ) {
		$url = "{$this->api_base}/projects/{$project_id}/columns";
		return $this->post( $url, $args, 'application/vnd.github.inertia-preview+json' );
	}

	public function project_cards( $column_id, $args = [] ) {
		$url = "{$this->api_base}/projects/columns/{$column_id}/cards";
		return $this->get( $url, $args, 'application/vnd.github.inertia-preview+json' );
	}

	public function create_project_card( $column_id, $args = [] ) {
		$url = "{$this->api_base}/projects/columns/{$column_id}/cards";
		return $this->post( $url, $args, 'application/vnd.github.inertia-preview+json' );
	}

	/**
	 * get all files for a pull request
	 * see also: https://developer.github.com/v3/pulls/#list-pull-requests-files
	 *
	 * @param string $repo the name of the repository
	 * @param object $pull the pull request object
	 * @return array containing file objects
	 */
	public function pull_request_files( $repo, $pull ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/{$pull->number}/files";
		// Make sure to get all the files from the PR as by default only 30 were returned.
		return $this->get( $url, [ 'per_page' => 3000 ] );
	}//end pull_request_files

	/**
	 * Get review comments from a given pull request
	 *
	 * @param string $repo the name of the repository
	 * @param object $pull the pull request object
	 * @return array containing review comments
	 */
	public function review_comments( $repo, $issue_number, $review_id ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/{$issue_number}/reviews/{$review_id}/comments";
		return $this->get( $url );
	}

	/**
	 * get all comments associated with a pull request
	 * see also: https://developer.github.com/v3/issues/comments/#list-comments-on-an-issue
	 *
	 * @param string $repo the name of the repository
	 * @param object $pull the pull request object
	 * @return array containing comment objects
	 */
	public function comments( $repo, $pull, $args = [] ) {
		$args['per_page'] = isset( $args['per_page'] ) ? $args['per_page'] : 250;

		$url = "{$this->api_base}/repos/{$this->org}/$repo/issues/{$pull->number}/comments";
		return $this->get( $url, $args );
	}// end comments

	/**
	 * add a comment to an issue or pull request
	 * see also: https://developer.github.com/v3/issues/comments/#create-a-comment
	 *
	 * @param string $repo the name of the repository
	 * @param object $issue the issue or pull request object
	 * @return array results of the API call
	 */
	public function add_comment( $repo, $issue, $comment_body ) {
		$data = array(
			'body' => $comment_body,
		);
		$url = "{$this->api_base}/repos/{$this->org}/$repo/issues/{$issue->number}/comments";

		return $this->post( $url, $data );
	}// end add_comment

	/**
	 * delete a comment
	 * see also: https://developer.github.com/v3/issues/comments/#delete-a-comment
	 *
	 * @param string $repo the name of the repository
	 * @param object $comment the comment object to delete
	 * @return array results of the API call
	 */
	public function delete_comment( $repo, $comment ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/comments/{$comment->id}";
		return $this->delete( $url );
	}// end delete_comment

	/**
	 * get all statuses on a pull request
	 * see also: https://developer.github.com/v3/repos/statuses/
	 *
	 * @param object $pull the pull request object
	 * @return array containing status objects
	 */
	public function statuses( $pull ) {
		return $this->get( $pull->statuses_url );
	}//end statuses

	/**
	 * get the full statuses on a pull request
	 * note: this will contain statuses that apply to more than just the latest commit
	 * see also: https://developer.github.com/v3/repos/statuses/#get-the-combined-status-for-a-specific-ref
	 *
	 * @param string $repo the name of the repository
	 * @param object $pull the pull request object
	 * @return array containing full status objects
	 */
	public function full_statuses( $repo, $pull ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/commits/{$pull->head->sha}/status";
		return $this->get( $url, [], true );
	}//end full_statuses

	/**
	 * get all labels on an issue / pull request
	 * see also: https://developer.github.com/v3/issues/labels/#list-labels-on-an-issue
	 *
	 * @param string $repo the name of the repository
	 * @param string $issue_number the issue / pull request number
	 * @return array containing status objects
	 */
	public function labels( $repo, $issue_number ) {
		$labels_arr = [];
		$url = "{$this->api_base}/repos/{$this->org}/$repo/issues/{$issue_number}/labels";
		$labels = $this->get( $url, [], true );
		foreach ( $labels as $label ) {
			$labels_arr[] = $label->name;
		}// end foreach
		return $labels_arr;
	}

	/**
	 * get all labels in a repo
	 * see also: https://developer.github.com/v3/issues/labels/#list-all-labels-for-this-repository
	 *
	 * @param string $repo the name of the repository
	 * @return array containing label objects
	 */
	public function repo_labels( $repo ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/labels";
		$labels = $this->get( $url, [], true );
		return $labels;
	}

	/**
	 * assign labels to an issue
	 *
	 * @param string $repo the name of the repository
	 * @param int $issue_number the issue / pull request number
	 * @param array $labels The labels to assign to the issue / pull request
	 */
	public function assign_labels( $repo, $issue_number, $labels ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/issues/{$issue_number}/labels";
		return $this->post( $url, $labels );
	}

	/**
	 * Create a repo label
	 *
	 * @param $repo
	 * @param $args
	 * @return array|object
	 */
	public function create_label( $repo, $args ) {
		$url = "{$this->api_base}/repos/{$this->org}/{$repo}/labels";
		return $this->post( $url, $args );
	}

	/**
	 * Update a repo label
	 *
	 * @param $repo
	 * @param $label
	 * @param $args
	 *
	 * @return array|object
	 */
	public function update_label( $repo, $label, $args ) {
		$label = str_replace( ' ', '%20', $label );
		$url = "{$this->api_base}/repos/{$this->org}/{$repo}/labels/{$label}";
		return $this->patch( $url, $args, 'application/vnd.github.symmetra-preview+json' );
	}

	/**
	 * Delete a repo label
	 *
	 * @param $repo
	 * @param $label
	 *
	 * @return array|object
	 */
	public function delete_label( $repo, $label ) {
		$label = str_replace( ' ', '%20', $label );
		$url = "{$this->api_base}/repos/{$this->org}/{$repo}/labels/{$label}";
		return $this->delete( $url );
	}

	/**
	 * get all requested reviewers
	 *
	 * @param string $repo the name of the repository
	 * @param string $issue_number the issue / pull request number
	 * @return array containing review objects
	 */
	public function requested_reviewers( $repo, $issue_number ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/{$issue_number}/requested_reviewers";
		return $this->get( $url, [] );
	}

	/**
	 * get all reviews
	 *
	 * @param string $repo the name of the repository
	 * @param string $issue_number the issue / pull request number
	 * @return array containing review objects
	 */
	public function reviews( $repo, $issue_number ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/{$issue_number}/reviews";
		return $this->get( $url, [], 'application/vnd.github.black-cat-preview+json' );
	}

	/**
	 * create a review
	 *
	 * @param string $repo the name of the repository
	 * @param string $issue_number the issue / pull request number
	 * @param array $args
	 * @return array containing review objects
	 */
	public function create_review( $repo, $issue_number, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/{$issue_number}/reviews";
		return $this->post( $url, $args );
	}

	/**
	 * update a review
	 *
	 * @param string $repo the name of the repository
	 * @param string $pr the issue / pull request
	 * @param string $review_id
	 * @param array $args
	 * @return array containing review objects
	 */
	public function update_review( $repo, $pull, $review_id, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/{$pull->number}/reviews/{$review_id}";
		return $this->put( $url, $args );
	}

	/**
	 * dismiss a review
	 *
	 * @param string $repo the name of the repository
	 * @param string $issue_number the issue / pull request number
	 * @param string $review_id
	 *
	 * @return array containing review objects
	 */
	public function dismiss_review( $repo, $issue_number, $review_id, $args = [] ) {
		$url = "{$this->api_base}/repos/{$this->org}/$repo/pulls/{$issue_number}/reviews/{$review_id}/dismissals";
		return $this->put( $url, $args );
	}

	/**
	 * get all milestones for a repo
	 * see also: https://developer.github.com/v3/issues/milestones/#list-milestones-for-a-repository
	 *
	 * @param string $repo the name of the repository
	 * @return array containing milestone objects
	 */
	public function repo_milestones( $repo ) {
		$milestones_arr = [];
		$url = "{$this->api_base}/repos/{$this->org}/$repo/milestones";
		$milestones = $this->get( $url, [], true );
		foreach ( $milestones as $milestone ) {
			$milestones_arr[ $milestone->title ] = $milestone;
		}// end foreach
		return $milestones_arr;
	}//end repo_milestones

	/**
	 * add a status to a pull request
	 * see also: https://developer.github.com/v3/repos/statuses/#create-a-status
	 *
	 * @param object $pull the pull request object
	 * @param array $data associative array defining the status, must include: state, target_url, description, and context
	 */
	public function add_status( $pull, $data ) {
		$url = $pull->statuses_url;
		$return = $this->post( $url, $data, true );
		return $return;
	}// end add_status

	/**
	 * Execute a GET API call
	 *
	 * @param string $url API URL
	 * @param array $args arguments to add to the query string
	 * @param boolean $beta (optional) defauls to false, if true it adds the required header to use GitHub beta APIs
	 * @return array|object results from API call
	 */
	private function get( $url, $args = [], $beta = false ) {
		$accept = null;

		if ( isset( $args['headers'] ) ) {
			$accept = $args['headers']['Accept'];
			unset( $args['headers'] );
		}

		$args['oauth_token'] = $this->oauth_token;
		$query = http_build_query( $args );
		$url .= '?' . $query;

		if ( $this->debug ) {
			echo "GET: $url\n";
		}// end if

		$extra = '';
		if ( $beta && true === $beta ) {
			$extra .= " -H 'Accept: application/vnd.github.shadow-cat-preview+json' ";
		} elseif ( $beta ) {
			$extra .= " -H 'Accept: {$beta}' ";
		} elseif ( $accept ) {
			$extra .= " -H 'Accept: {$accept}' ";
		}

		$result = `curl $extra -s -S "$url"`;
		return json_decode( $result );
	}// end get

	/**
	 * Execute a GET API call
	 *
	 * @param string $url API URL
	 * @param array $args arguments to add to the query string
	 * @param boolean $beta (optional) defauls to false, if true it adds the required header to use GitHub beta APIs
	 * @return array|object results from API call
	 */
	private function get_diff( $url, $args = [] ) {
		$args['oauth_token'] = $this->oauth_token;
		$query = http_build_query( $args );
		$url .= '?' . $query;

		if ( $this->debug ) {
			echo "GET: $url\n";
		}// end if

		$extra = " -H 'Accept: application/vnd.github.v3.diff' ";

		$result = `curl $extra -s -S "$url"`;
		return $result;
	}

	/**
	 * Execute a POST API call
	 *
	 * @param string $url API URL
	 * @param array $data data to send
	 * @param boolean $beta (optional) defauls to false, if true it adds the required header to use GitHub beta APIs
	 * @return array|object results from API call
	 */
	private function post( $url, $data, $beta = false ) {
		$data = escapeshellarg( json_encode( $data ) );

		$extra = '';
		if ( $beta && true === $beta ) {
			$extra .= " -H 'Accept: application/vnd.github.black-cat-preview+json' ";
		} elseif ( $beta ) {
			$extra .= " -H 'Accept: {$beta}' ";
		}

		if ( $this->debug ) {
			echo "POST: $url --data $data\n";
			return;
		}// end if

		$result = `curl -s -S -X POST $extra '$url?oauth_token={$this->oauth_token}' --data $data`;
		return json_decode( $result );
	}// end post

	/**
	 * Execute a GET API call
	 *
	 * @param string $url API URL
	 * @return array|object results from API call
	 */
	private function delete( $url ) {
		if ( $this->debug ) {
			echo "DELETE: $url\n";
			return;
		}// end if

		$result = `curl -s -S -X DELETE '$url?oauth_token={$this->oauth_token}'`;
		return json_decode( $result );
	}// end delete

	/**
	 * Execute a PATCH API call
	 *
	 * @param string $url API URL
	 * @return array|object results from API call
	 */
	private function patch( $url, $data, $beta = false ) {
		$data = escapeshellarg( json_encode( $data ) );

		$extra = '';
		if ( $beta && true === $beta ) {
			$extra .= " -H 'Accept: application/vnd.github.symmetra-preview+json' ";
		} elseif ( $beta ) {
			$extra .= " -H 'Accept: {$beta}' ";
		}

		if ( $this->debug ) {
			echo "PATCH: $url --data $data\n";
			return;
		}// end if

		$result = `curl -s -S -X PATCH $extra '$url?oauth_token={$this->oauth_token}' --data $data`;
		return json_decode( $result );
	}// end patch

	/**
	 * Execute a PUT API call
	 *
	 * @param string $url API URL
	 * @return array|object results from API call
	 */
	private function put( $url, $data ) {
		$data = escapeshellarg( json_encode( $data ) );

		if ( $this->debug ) {
			echo "PUT: $url --data $data\n";
			return;
		}// end if

		$result = `curl -s -S -X PUT '$url?oauth_token={$this->oauth_token}' --data $data`;
		return json_decode( $result );
	}// end patch
}// end class
