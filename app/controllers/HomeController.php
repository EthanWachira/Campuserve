<?php
class HomeController extends Controller
{
    public function indexAction(): void
    {
        $this->render('home/landing', [
            'title' => 'Campusserve: student support that keeps track',
            'routing' => Ticket::categories(),
            'user' => Auth::user(),
        ], 'layouts/public');
    }
}
