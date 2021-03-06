<?php

namespace Gitlab\Model;

use Gitlab\Api\MergeRequests;
use Gitlab\Client;

/**
 * @final
 *
 * @property-read int $id
 * @property-read int $iid
 * @property-read string $target_branch
 * @property-read string $source_branch
 * @property-read int|string $project_id
 * @property-read string $title
 * @property-read string $description
 * @property-read bool $closed
 * @property-read bool $merged
 * @property-read string $state
 * @property-read int|string $source_project_id
 * @property-read int|string $target_project_id
 * @property-read int $upvotes
 * @property-read int $downvotes
 * @property-read array $labels
 * @property-read array $head_pipeline
 * @property-read bool $blocking_discussions_resolved
 * @property-read bool $merge_when_pipeline_succeeds
 * @property-read bool $should_remove_source_branch
 * @property-read bool $rebase_in_progress
 * @property-read bool $work_in_progress
 * @property-read User|null $author
 * @property-read User|null $assignee
 * @property-read Project $project
 * @property-read Milestone|null $milestone
 * @property-read File[]|null $files
 */
class MergeRequest extends AbstractModel implements Noteable, Notable
{
    /**
     * @var string[]
     */
    protected static $properties = [
        'id',
        'iid',
        'target_branch',
        'source_branch',
        'project_id',
        'title',
        'description',
        'closed',
        'merged',
        'head_pipeline',
        'author',
        'assignee',
        'project',
        'state',
        'source_project_id',
        'target_project_id',
        'blocking_discussions_resolved',
        'merge_when_pipeline_succeeds',
        'should_remove_source_branch',
        'rebase_in_progress',
        'work_in_progress',
        'upvotes',
        'downvotes',
        'labels',
        'milestone',
        'files',
    ];

    /**
     * @param Client  $client
     * @param Project $project
     * @param array   $data
     *
     * @return MergeRequest
     */
    public static function fromArray(Client $client, Project $project, array $data)
    {
        $mr = new static($project, $data['id'], $client);

        if (isset($data['author'])) {
            $data['author'] = User::fromArray($client, $data['author']);
        }

        if (isset($data['assignee'])) {
            $data['assignee'] = User::fromArray($client, $data['assignee']);
        }

        if (isset($data['milestone'])) {
            $data['milestone'] = Milestone::fromArray($client, $project, $data['milestone']);
        }

        if (isset($data['files'])) {
            $files = [];
            foreach ($data['files'] as $file) {
                $files[] = File::fromArray($client, $project, $file);
            }

            $data['files'] = $files;
        }

        return $mr->hydrate($data);
    }

    /**
     * @param Project     $project
     * @param int|null    $iid
     * @param Client|null $client
     *
     * @return void
     */
    public function __construct(Project $project, $iid = null, Client $client = null)
    {
        $this->setClient($client);
        $this->setData('project', $project);
        $this->setData('iid', $iid);
    }

    /**
     * @return MergeRequest
     */
    public function show()
    {
        $data = $this->client->mergeRequests()->show($this->project->id, $this->iid);

        return static::fromArray($this->getClient(), $this->project, $data);
    }

    /**
     * @param array $params
     *
     * @return MergeRequest
     */
    public function update(array $params)
    {
        $data = $this->client->mergeRequests()->update($this->project->id, $this->iid, $params);

        return static::fromArray($this->getClient(), $this->project, $data);
    }

    /**
     * @param string|null $note
     *
     * @return MergeRequest
     */
    public function close($note = null)
    {
        if (null !== $note) {
            $this->addNote($note);
        }

        return $this->update([
            'state_event' => 'close',
        ]);
    }

    /**
     * @return MergeRequest
     */
    public function reopen()
    {
        return $this->update([
            'state_event' => 'reopen',
        ]);
    }

    /**
     * @return MergeRequest
     */
    public function open()
    {
        return $this->reopen();
    }

    /**
     * @param string|null $message
     *
     * @return MergeRequest
     */
    public function merge($message = null)
    {
        $data = $this->client->mergeRequests()->merge($this->project->id, $this->iid, [
            'merge_commit_message' => $message,
        ]);

        return static::fromArray($this->getClient(), $this->project, $data);
    }

    /**
     * @return MergeRequest
     */
    public function merged()
    {
        return $this->update([
            'state_event' => 'merge',
        ]);
    }

    /**
     * @param string $body
     *
     * @return Note
     */
    public function addNote($body)
    {
        $data = $this->client->mergeRequests()->addNote($this->project->id, $this->iid, $body);

        return Note::fromArray($this->getClient(), $this, $data);
    }

    /**
     * @param string      $comment
     * @param string|null $created_at
     *
     * @return Note
     *
     * @deprecated since version 9.18 and will be removed in 10.0. Use the addNote() method instead.
     */
    public function addComment($comment, $created_at = null)
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 9.18 and will be removed in 10.0. Use the addNote() method instead.', __METHOD__), E_USER_DEPRECATED);

        if (null === $created_at) {
            return $this->addNote($comment);
        }

        $data = $this->client->mergeRequests()->addComment($this->project->id, $this->iid, $comment);

        return Note::fromArray($this->getClient(), $this, $data);
    }

    /**
     * @return Note[]
     *
     * @deprecated since version 9.18 and will be removed in 10.0. Use the result pager with the conventional API methods.
     */
    public function showComments()
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 9.18 and will be removed in 10.0. Use the result pager with the conventional API methods.', __METHOD__), E_USER_DEPRECATED);

        $notes = [];
        $data = $this->client->mergeRequests()->showNotes($this->project->id, $this->iid);

        foreach ($data as $note) {
            $notes[] = Note::fromArray($this->getClient(), $this, $note);
        }

        return $notes;
    }

    /**
     * @return bool
     */
    public function isClosed()
    {
        return MergeRequests::STATE_CLOSED === $this->state || MergeRequests::STATE_MERGED === $this->state;
    }

    /**
     * @return MergeRequest
     */
    public function changes()
    {
        $data = $this->client->mergeRequests()->changes($this->project->id, $this->iid);

        return static::fromArray($this->getClient(), $this->project, $data);
    }
}
