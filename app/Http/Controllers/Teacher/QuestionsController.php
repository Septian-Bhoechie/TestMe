<?php

namespace App\Http\Controllers\Teacher;

use App\Exam;
use App\Http\Controllers\Controller;
use App\Option;
use App\Question;
use App\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class QuestionsController extends Controller
{
    private $domDocument;

    public function __construct()
    {
        $this->domDocument = new \domdocument('1.0', 'utf-8');
    }


    /**
     * Index Page
     *
     * @return \Illuminate\Http\Response
     */
    public function index(string $subjectAlias, int $classId)
    {
        $subject = Subject::findThroughAlias($subjectAlias);
        $current_class = $subject->classes()->where('class_id', $classId)->firstOrFail();

        abort_if(! Gate::allows('view-subject-details', [$subject->id, $classId]), 404, "Page not found");

        $exams = Exam::where('subject_id',$subject->id)->where('class_id',$classId)->orderByDesc('date')->orderBy('updated_at','desc')->with('subject','class')->get();

        $classes = auth()->user()->isSuperAdmin()
                        ? $subject->classes
                        : $subject->adminSubjects()->where('admin_id', auth()->id())->first()->classes;


        (count($exams) > 0) ? session()->put('exam_id', $exams[0]->id) : session()->forget('exam_id');

        return view('admin.questions', compact('subject','class_id','current_class','classes','exams'));
    }

    /**
     * Create a new question for an upcoming exam.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $question = $this->loadAsHtml($request->question)->storeImages()->save();

        DB::beginTransaction();

        try {
            $createdQuestion = Question::create([
                'exam_id' => session()->get('exam_id'),
                'body' => $question,
            ]);

            foreach ($request->options as $key => $option) {
                $option = $this->loadAsHtml($option)->storeImages()->save();

                Option::create([
                    'question_id' => $createdQuestion->id,
                    'body' => $option,
                    'isCorrect' => (bool) $request->correct === $key
                ]);
            }

            DB::commit();

            $createdQuestion->load('options');

            return $this->sendSuccessResponse("Question added successfully", $createdQuestion, 201);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->sendErrorResponse("An error was encountered submitting this question: {$e->getMessage()}");
        }
    }

    /**
     * Update a question
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $question = Question::where('id',$id)->with('options')->firstOrFail();

        $questionBody = $this->loadAsHtml($request->question)->storeImages()->save();

        DB::beginTransaction();

        try {
            $question->update([
                'question' => $questionBody,
            ]);

            foreach ($request->options as $key => $option) {
                $option = $this->loadAsHtml($option)->storeImages()->save();

                $question->options[$key]->body = $option;
                $question->options[$key]->isCorrect = (bool) $request->correct === $key;

                $question->push();
            }

            DB::commit();

            $question->refresh();

            return $this->sendSuccessResponse("Question updated successfully", $question);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->sendErrorResponse("An error was encountered updating this question: {$e->getMessage()}");
        }
    }

    /**
     * Delete a question
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $question = Question::findOrFail($id);
        $question->delete();

        return $this->sendSuccessResponse("Question deleted successfully", null, 204);
    }

    /**
     * Loads a string as an HTML DOM document
     *
     * @param string $text
     * @return self
     */
    private function loadAsHtml(string $text): self
    {
        $parsedHtml = mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8');

        libxml_use_internal_errors(true); //for the math tags

        $this->domDocument->loadHtml($parsedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();

        return $this;
    }

    /**
     * Decodes each base64 image string in the document and stores it on the server
     *
     * @return self
     */
    private function storeImages(): self
    {
        $images = $this->domDocument->getElementsByTagName('img');

        foreach($images as $image){
            $this->upload($image);
        }

        return $this;
    }

    private function upload($image): void
    {
        $base64Data = $image->getAttribute('src');

        if (strpos($base64Data, 'data:image') !== false) { //check if it's a base64 image

            list($type, $base64Data) = explode(';', $base64Data);
            list($format, $base64String) = explode(',', $base64Data);

            $imageData = base64_decode($base64String);

            $fileName = Storage::putFile('question-uploads', $imageData);

            $image->removeattribute('src');
            $image->setattribute('src', asset("storage/app/public/{$fileName}"));
        }
    }

    private function save(): string
    {
        return $this->domDocument->saveHTML();
    }
}