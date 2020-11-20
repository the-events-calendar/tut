<?php

namespace TUT\Github\Api\Repository;

use Github\Api\AbstractApi;

class Template extends AbstractApi {

	/**
	 * List all repositories for an organization.
	 *
	 * @link http://developer.github.com/v3/repos/#list-organization-repositories
	 *
	 * @param string $template_owner User or organization that owns the template repo.
	 * @param string $template_repo The template repo to copy.
	 * @param array  $params Parameters to send along to GitHub.
	 *     $params = [
	 *       'owner'       => (string) The owner/org to copy the repo to.
	 *       'name'        => (string) The repo to create.
	 *       'description' => (string) The description of the repo.
	 *       'private'     => (bool) Whether the repo is a private repo or not.
	 *     ];
	 * @param string $org The org to copy to the repo to.
	 * @param string $repo The repo to create.
	 * @param string $description The description of the repo.
	 * @param bool   $private Whether the repo is a private repo or not.
	 *
	 * @return array
	 */
	public function createRepo(
		$template_owner,
		$template_repo,
		$params = []
	) {
		return $this->post(
			"/repos/{$template_owner}/{$template_repo}/generate",
			$params,
			[
				'Accept' => 'application/vnd.github.baptiste-preview+json',
			]
		);
	}
}
